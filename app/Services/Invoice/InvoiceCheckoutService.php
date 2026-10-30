<?php

namespace App\Services\Invoice;

use App\Jobs\SendInvoiceWebhook;
use App\Models\Invoice;
use App\Models\InvoiceCheckout;
use App\Models\InvoiceCheckoutItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceSetting;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\DuitkuClient;
use App\Services\Subscription\AppAccess;
use App\Services\Wallet\SettlementCalendar;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Bayar beberapa tagihan sekaligus dari menu Tagihan (satu order Duitku, awalan TGH).
 * Pola sama dengan InvoicePaymentService: invoice dikunci saat dibuat, lunas hanya dari Duitku, diproses sekali,
 * saldo owner dikreditkan sekali. Satu invoice tidak bisa ada di dua pembayaran terbuka (gabungan maupun link biasa).
 */
class InvoiceCheckoutService
{
    public const PREFIX = 'TGH';
    private const EXPIRY_GRACE_MINUTES = 10;

    public function __construct(private readonly WalletService $wallets, private readonly InvoiceService $invoices)
    {
    }

    public function create(User $user, string $branchId, array $invoiceIds): InvoiceCheckout
    {
        if (! PayerInvoices::verified($user) || ! app(AppAccess::class)->branchActive($branchId, 'invoices')) {
            throw new RuntimeException('Pembayaran online untuk tagihan ini sedang tidak tersedia. Silakan hubungi penagih.');
        }

        $client = DuitkuClient::make(forCallback: true);
        $invoiceIds = array_values(array_unique($invoiceIds));

        $checkout = DB::transaction(function () use ($user, $branchId, $invoiceIds) {
            // Dikunci urut id: dua pembayaran untuk tagihan yang sama tidak bisa dibuat bersamaan.
            $invoices = PayerInvoices::query($user)->where('branch_id', $branchId)->whereKey($invoiceIds)
                ->orderBy('id')->lockForUpdate()->get()->load('category');

            if ($invoices->count() !== count($invoiceIds) || $invoices->contains(fn (Invoice $i) => ! $i->isUnpaid() || $i->expires_at?->isPast() || $i->isPastDueClosed())) {
                throw new RuntimeException('Ada tagihan yang sudah berubah. Muat ulang halaman dulu ya.');
            }

            if ($errors = PayerInvoices::sequenceErrors(PayerInvoices::payable($user, $branchId), $invoiceIds)) {
                throw ValidationException::withMessages(['invoices' => $errors]);
            }

            $busy = InvoiceCheckoutItem::whereIn('invoice_id', $invoiceIds)->whereHas('checkout', fn ($q) => $q->open())->exists()
                || InvoicePayment::whereIn('invoice_id', $invoiceIds)->where('status', InvoicePayment::STATUS_PENDING)->where('expires_at', '>', now())->exists();

            if ($busy) {
                throw new RuntimeException('Sebagian tagihan sedang menunggu pembayaran di transaksi lain. Selesaikan pembayaran itu dulu, atau tunggu sampai batas waktunya lewat.');
            }

            $amount = $invoices->sum(fn (Invoice $i) => $i->totalDue());
            $setting = InvoiceSetting::current();

            if ($amount < $setting->min_amount || $amount > $setting->max_amount) {
                throw new RuntimeException($setting->limitMessage($amount).' Coba ubah tagihan yang dipilih.');
            }

            $checkout = new InvoiceCheckout();
            $checkout->forceFill([
                'user_id' => $user->getKey(),
                'branch_id' => $branchId,
                'merchant_order_id' => $this->uniqueOrderId(),
                'amount' => $amount,
                'late_fee' => $invoices->sum(fn (Invoice $i) => $i->currentLateFee()),
                'status' => InvoiceCheckout::STATUS_PENDING,
                'expires_at' => now()->addMinutes(PaymentGatewaySetting::duitku()->expiry_minutes),
            ])->save();

            // Nominal & denda dibekukan per invoice saat ini.
            foreach ($invoices as $invoice) {
                (new InvoiceCheckoutItem())->forceFill([
                    'checkout_id' => $checkout->getKey(),
                    'invoice_id' => $invoice->getKey(),
                    'amount' => $invoice->amount,
                    'late_fee' => $invoice->currentLateFee(),
                    'total' => $invoice->totalDue(),
                ])->save();
            }

            return $checkout;
        });

        try {
            $result = $client->createInvoice([
                'amount' => $checkout->amount,
                'orderId' => $checkout->merchant_order_id,
                'product' => 'Pembayaran '.$checkout->items()->count().' tagihan',
                'customer' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->handphone],
                // Duitku: total semua baris harus sama dengan paymentAmount.
                'items' => $checkout->items()->with('invoice:id,description')->get()
                    ->map(fn ($item) => ['name' => Str::limit($item->invoice->description, 90, ''), 'price' => $item->total, 'quantity' => 1])->all(),
                'returnUrl' => route('bills.return', $checkout),
            ]);
        } catch (Throwable $e) {
            report($e);
            $result = ['paymentUrl' => null, 'reference' => null];
        }

        if (! $result['paymentUrl']) {
            $checkout->forceFill(['status' => InvoiceCheckout::STATUS_FAILED])->save();

            throw new RuntimeException('Halaman pembayaran belum bisa dibuka. Tidak ada dana yang terpotong, silakan coba lagi sebentar lagi.');
        }

        $checkout->forceFill(['payment_url' => $result['paymentUrl'], 'provider_reference' => $result['reference']])->save();

        return $checkout;
    }

    /** Callback Duitku untuk nomor TGH... (sudah dicatat & tanda tangannya valid). */
    public function handleCallback(array $payload, PaymentCallback $log, DuitkuClient $client): array
    {
        $checkout = InvoiceCheckout::where('merchant_order_id', $payload['merchantOrderId'])->first();

        if (! $checkout || (int) round((float) $payload['amount']) !== $checkout->amount) {
            $log->update(['result' => $checkout ? 'amount_mismatch' : 'not_found']);

            return [200, 'OK'];
        }

        if (($payload['resultCode'] ?? null) !== '00') {
            $failed = InvoiceCheckout::whereKey($checkout->getKey())->where('status', InvoiceCheckout::STATUS_PENDING)->update(['status' => InvoiceCheckout::STATUS_FAILED]);
            $log->update(['result' => $failed ? 'failed' : 'already_processed']);

            return [200, 'OK'];
        }

        if ($this->open($checkout) && ($reason = $client->unpaidReason($checkout->merchant_order_id, $checkout->amount))) {
            $log->update(['result' => 'not_confirmed', 'message' => $reason]);

            return [409, 'Payment not confirmed'];
        }

        $log->update(['result' => $this->markPaid($checkout, $payload['paymentCode'] ?? null, $payload['reference'] ?? null)]);

        return [200, 'OK'];
    }

    /** Cek langsung ke Duitku (saat pembayar kembali dari Duitku). */
    public function refresh(InvoiceCheckout $checkout): bool
    {
        try {
            $client = DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            return false;
        }

        if ($this->open($checkout) && $client->unpaidReason($checkout->merchant_order_id, $checkout->amount) === null) {
            $this->markPaid($checkout, null, null);
        }

        return in_array($checkout->refresh()->status, [InvoiceCheckout::STATUS_PAID, InvoiceCheckout::STATUS_REFUND_NEEDED], true);
    }

    /** Scheduler: lewat batas dicek ke Duitku; yang tidak dibayar ditutup supaya tagihannya bisa dibayar lagi. */
    public function expireStale(): array
    {
        $summary = ['paid' => 0, 'expired' => 0];

        InvoiceCheckout::where('status', InvoiceCheckout::STATUS_PENDING)
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->chunkById(100, function ($checkouts) use (&$summary) {
                foreach ($checkouts as $checkout) {
                    if ($this->refresh($checkout)) {
                        $summary['paid']++;
                    } elseif (InvoiceCheckout::whereKey($checkout->getKey())->where('status', InvoiceCheckout::STATUS_PENDING)->update(['status' => InvoiceCheckout::STATUS_EXPIRED])) {
                        $summary['expired']++;
                    }
                }
            });

        return $summary;
    }

    /** Dipakai juga oleh link bayar biasa supaya satu invoice tidak terbayar dua kali. */
    public static function openCheckoutFor(string $invoiceId): ?InvoiceCheckout
    {
        return InvoiceCheckout::open()->whereHas('items', fn ($q) => $q->where('invoice_id', $invoiceId))->first();
    }

    /**
     * Satu-satunya jalur ke "lunas": semua invoice di checkout lunas bersamaan; yang ternyata sudah lunas/dibatalkan
     * lebih dulu ditandai perlu refund. Biaya layanan dihitung dari total lalu dibagi proporsional ke tiap invoice.
     */
    private function markPaid(InvoiceCheckout $checkout, ?string $method, ?string $providerReference): string
    {
        $result = DB::transaction(function () use ($checkout, $method, $providerReference) {
            $locked = InvoiceCheckout::with('branch.user')->whereKey($checkout->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->open($locked)) {
                return 'already_processed';
            }

            $items = $locked->items()->orderBy('invoice_id')->get();
            $invoices = Invoice::whereKey($items->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $fee = InvoiceSetting::current()->feeFor($locked->amount);
            $given = 0;
            $refund = [];

            foreach ($items->values() as $index => $item) {
                // Sisa pembulatan biaya masuk ke invoice terakhir, supaya jumlahnya pas.
                $share = $index === $items->count() - 1 ? $fee - $given : intdiv($fee * $item->total, max(1, $locked->amount));
                $given += $share;
                $invoice = $invoices->get($item->invoice_id);
                $accepted = $invoice && in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_EXPIRED], true);

                if ($accepted) {
                    $invoice->forceFill([
                        'status' => Invoice::STATUS_PAID, 'late_fee' => $item->late_fee, 'paid_amount' => $item->total,
                        'fee' => $share, 'net_amount' => $item->total - $share, 'payment_method' => $method, 'paid_at' => now(),
                    ])->save();
                } else {
                    $refund[] = $invoice?->number ?? $item->invoice_id;
                }

                $item->forceFill(['result' => $accepted ? 'paid' : 'refund_needed'])->save();
            }

            $locked->forceFill([
                'status' => $refund ? InvoiceCheckout::STATUS_REFUND_NEEDED : InvoiceCheckout::STATUS_PAID,
                'payment_method' => $method,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
                'fee' => $fee,
                'net_amount' => $locked->amount - $fee,
                'paid_at' => now(),
            ])->save();

            if ($locked->net_amount > 0) {
                // Potongan QRIS (kalau dibayar QRIS) ditangani creditPayment().
                $this->wallets->creditPayment(
                    $locked->branch->user,
                    $locked->net_amount,
                    $locked->amount,
                    WalletTransaction::TYPE_INVOICE_PAYMENT,
                    'invoice-checkout:'.$locked->getKey(),
                    $locked,
                    'Pembayaran '.$items->count().' tagihan '.$locked->merchant_order_id.' Rp '.number_format($locked->amount, 0, ',', '.')
                        .' (biaya layanan Rp '.number_format($fee, 0, ',', '.').')'
                        .($refund ? ' · '.implode(', ', $refund).' SUDAH LUNAS SEBELUMNYA, mohon refund bagian ini' : ''),
                    paymentMethod: $method,
                    availableAt: SettlementCalendar::availableAt($method),
                );
            }

            return $refund ? 'refund_needed' : 'paid';
        });

        // Webhook & email bukti bayar sama seperti bayar satu per satu.
        if ($result !== 'already_processed') {
            foreach ($checkout->items()->whereIn('result', ['paid', 'refund_needed'])->with('invoice')->get() as $item) {
                if (! $item->invoice) {
                    continue;
                }

                if ($item->result === 'paid') {
                    SendInvoiceWebhook::for($item->invoice, 'invoice.paid');
                    $this->invoices->email($item->invoice, 'paid');
                }

                $this->invoices->notifyOwner($item->invoice, $item->result === 'paid' ? 'paid' : 'refund', $item->total);
            }
        }

        return $result;
    }

    private function open(InvoiceCheckout $checkout): bool
    {
        return in_array($checkout->status, [InvoiceCheckout::STATUS_PENDING, InvoiceCheckout::STATUS_EXPIRED, InvoiceCheckout::STATUS_FAILED], true);
    }

    private function uniqueOrderId(): string
    {
        do {
            $id = self::PREFIX.now()->format('ymd').strtoupper(Str::random(10));
        } while (InvoiceCheckout::where('merchant_order_id', $id)->exists());

        return $id;
    }
}
