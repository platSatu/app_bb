<?php

namespace App\Services\Invoice;

use App\Jobs\SendInvoiceWebhook;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceSetting;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\WalletTransaction;
use App\Services\Payment\DuitkuClient;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Alur uang invoice: bayar (satu order Duitku per percobaan) -> callback / cek ulang ke Duitku
 * -> lunas sekali saja -> saldo owner dikreditkan (total dikurangi biaya layanan bizbos) -> webhook & email.
 * Status "lunas" hanya ditentukan dari Duitku (callback bertanda tangan atau cek status langsung).
 */
class InvoicePaymentService
{
    public const PREFIX = 'INV';
    private const EXPIRY_GRACE_MINUTES = 10;

    public function __construct(private readonly WalletService $wallets, private readonly InvoiceService $invoices)
    {
    }

    /** $timeout: batas tunggu Duitku (detik). API memakai batas pendek supaya website pemanggil tidak ikut timeout. */
    public function checkout(Invoice $invoice, string $returnUrl, int $timeout = 20): InvoicePayment
    {
        $client = DuitkuClient::make(forCallback: true);

        $payment = DB::transaction(function () use ($invoice) {
            $locked = Invoice::with('category')->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isUnpaid()) {
                throw new RuntimeException(match ($locked->status) {
                    Invoice::STATUS_PAID => 'Invoice ini sudah lunas. Terima kasih!',
                    Invoice::STATUS_CANCELLED => 'Invoice ini sudah dibatalkan oleh penagih.',
                    default => 'Invoice ini sudah kedaluwarsa. Silakan minta invoice baru ke penagih.',
                });
            }

            if ($locked->expires_at?->isPast()) {
                throw new RuntimeException('Batas waktu pembayaran invoice ini sudah lewat. Silakan minta invoice baru ke penagih.');
            }

            $total = $locked->totalDue();
            $setting = InvoiceSetting::current();

            if ($total < $setting->min_amount || $total > $setting->max_amount) {
                throw new RuntimeException('Nominal invoice di luar batas pembayaran online. Silakan hubungi penagih.');
            }

            // Order Duitku yang masih berlaku dengan nominal sama dipakai lagi, supaya tidak menumpuk.
            $open = $locked->payments()->where('status', InvoicePayment::STATUS_PENDING)->where('amount', $total)
                ->where('expires_at', '>', now()->addMinutes(2))->whereNotNull('payment_url')->latest()->first();

            if ($open) {
                return $open;
            }

            $payment = new InvoicePayment();
            $payment->forceFill([
                'invoice_id' => $locked->getKey(),
                'merchant_order_id' => $this->uniqueOrderId(),
                'amount' => $total,
                'late_fee' => $total - $locked->amount,
                'status' => InvoicePayment::STATUS_PENDING,
                'expires_at' => now()->addMinutes(PaymentGatewaySetting::duitku()->expiry_minutes),
            ])->save();

            return $payment;
        });

        if ($payment->payment_url) {
            return $payment;
        }

        try {
            $result = $client->createInvoice([
                'amount' => $payment->amount,
                'orderId' => $payment->merchant_order_id,
                'product' => Str::limit($invoice->number.' '.$invoice->description, 250, ''),
                'customer' => [
                    'name' => $invoice->customer_name,
                    // Duitku mewajibkan email; kalau customer tidak punya, pakai email perusahaan penagih.
                    'email' => $invoice->customer_email ?: ($invoice->company?->email ?: $invoice->branch?->user?->email),
                    'phone' => $invoice->customer_phone,
                ],
                'items' => array_values(array_filter([
                    ['name' => Str::limit($invoice->description, 90, ''), 'price' => $invoice->amount, 'quantity' => 1],
                    $payment->late_fee > 0 ? ['name' => 'Denda keterlambatan', 'price' => $payment->late_fee, 'quantity' => 1] : null,
                ])),
                'returnUrl' => $returnUrl,
            ], $timeout);
        } catch (Throwable $e) {
            report($e);
            $result = ['paymentUrl' => null, 'reference' => null, 'message' => 'Duitku tidak bisa dihubungi'];
        }

        if (! $result['paymentUrl']) {
            $payment->forceFill(['status' => InvoicePayment::STATUS_FAILED])->save();

            throw new RuntimeException('Halaman pembayaran belum bisa dibuka. Tidak ada dana yang terpotong, silakan coba lagi beberapa saat lagi.');
        }

        $payment->forceFill(['payment_url' => $result['paymentUrl'], 'provider_reference' => $result['reference']])->save();

        return $payment;
    }

    /** Callback Duitku untuk nomor INV... (sudah dicatat & tanda tangannya valid). */
    public function handleCallback(array $payload, PaymentCallback $log, DuitkuClient $client): array
    {
        $payment = InvoicePayment::where('merchant_order_id', $payload['merchantOrderId'])->first();

        if (! $payment) {
            $log->update(['result' => 'not_found']);

            return [200, 'OK'];
        }

        if ((int) round((float) $payload['amount']) !== $payment->amount) {
            $log->update(['result' => 'amount_mismatch']);

            return [200, 'OK'];
        }

        if (($payload['resultCode'] ?? null) !== '00') {
            $failed = InvoicePayment::whereKey($payment->getKey())->where('status', InvoicePayment::STATUS_PENDING)->update(['status' => InvoicePayment::STATUS_FAILED]);
            $log->update(['result' => $failed ? 'failed' : 'already_processed']);

            return [200, 'OK'];
        }

        if ($this->open($payment) && ($reason = $client->unpaidReason($payment->merchant_order_id, $payment->amount))) {
            $log->update(['result' => 'not_confirmed', 'message' => $reason]);

            return [409, 'Payment not confirmed'];
        }

        $log->update(['result' => $this->markPaid($payment, $payload['paymentCode'] ?? null, $payload['reference'] ?? null)]);

        return [200, 'OK'];
    }

    /**
     * Cek langsung ke Duitku untuk percobaan bayar yang masih terbuka (dipanggil saat customer kembali
     * dari Duitku atau menekan "Cek status"). Berguna juga saat callback belum sampai (mis. di localhost).
     */
    public function refresh(Invoice $invoice): bool
    {
        if (! $invoice->isUnpaid() && $invoice->status !== Invoice::STATUS_EXPIRED) {
            return $invoice->isPaid();
        }

        try {
            $client = DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            return false;
        }

        foreach ($invoice->payments()->whereIn('status', [InvoicePayment::STATUS_PENDING, InvoicePayment::STATUS_EXPIRED])->where('created_at', '>', now()->subDays(2))->get() as $payment) {
            if ($client->unpaidReason($payment->merchant_order_id, $payment->amount) === null) {
                $this->markPaid($payment, null, null);
            }
        }

        return $invoice->refresh()->isPaid();
    }

    /** Dijalankan scheduler: percobaan bayar lewat batas dicek ke Duitku, invoice API lewat batas ditutup. */
    public function expireStale(): array
    {
        $summary = ['paid' => 0, 'expired_payments' => 0, 'expired_invoices' => 0];

        try {
            $client = DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            $client = null;
        }

        InvoicePayment::where('status', InvoicePayment::STATUS_PENDING)
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->chunkById(100, function ($payments) use ($client, &$summary) {
                foreach ($payments as $payment) {
                    if ($client && $client->unpaidReason($payment->merchant_order_id, $payment->amount) === null) {
                        $this->markPaid($payment, null, null) === 'paid' && $summary['paid']++;
                    } elseif (InvoicePayment::whereKey($payment->getKey())->where('status', InvoicePayment::STATUS_PENDING)->update(['status' => InvoicePayment::STATUS_EXPIRED])) {
                        $summary['expired_payments']++;
                    }
                }
            });

        Invoice::where('status', Invoice::STATUS_UNPAID)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', InvoicePayment::STATUS_PENDING))
            ->chunkById(100, function ($invoices) use (&$summary) {
                foreach ($invoices as $invoice) {
                    $closed = Invoice::whereKey($invoice->getKey())->where('status', Invoice::STATUS_UNPAID)->update(['status' => Invoice::STATUS_EXPIRED, 'updated_at' => now()]);

                    if ($closed) {
                        $summary['expired_invoices']++;
                        SendInvoiceWebhook::for($invoice->refresh(), 'invoice.expired');
                    }
                }
            });

        return $summary;
    }

    /** Satu-satunya jalur ke "lunas". Saldo owner dikreditkan sekali per percobaan bayar (idempotency key). */
    private function markPaid(InvoicePayment $payment, ?string $method, ?string $providerReference): string
    {
        $result = DB::transaction(function () use ($payment, $method, $providerReference) {
            $lockedPayment = InvoicePayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->open($lockedPayment)) {
                return 'already_processed';
            }

            $invoice = Invoice::with('branch.user')->whereKey($lockedPayment->invoice_id)->lockForUpdate()->firstOrFail();
            // Invoice kedaluwarsa tetap diterima (customer memang membayar); sudah lunas/dibatalkan = perlu refund.
            $accepted = in_array($invoice->status, [Invoice::STATUS_UNPAID, Invoice::STATUS_EXPIRED], true);
            $fee = InvoiceSetting::current()->feeFor($lockedPayment->amount);
            $net = $lockedPayment->amount - $fee;

            $lockedPayment->forceFill([
                'status' => $accepted ? InvoicePayment::STATUS_PAID : InvoicePayment::STATUS_REFUND_NEEDED,
                'payment_method' => $method,
                'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                'paid_at' => now(),
            ])->save();

            if ($accepted) {
                $invoice->forceFill([
                    'status' => Invoice::STATUS_PAID,
                    'late_fee' => $lockedPayment->late_fee,
                    'paid_amount' => $lockedPayment->amount,
                    'fee' => $fee,
                    'net_amount' => $net,
                    'payment_method' => $method,
                    'paid_at' => now(),
                ])->save();
            }

            if ($net > 0) {
                $this->wallets->credit(
                    $invoice->branch->user,
                    $net,
                    WalletTransaction::TYPE_INVOICE_PAYMENT,
                    'invoice-payment:'.$lockedPayment->getKey(),
                    $invoice,
                    'Invoice '.$invoice->number.' dibayar Rp '.number_format($lockedPayment->amount, 0, ',', '.')
                        .' (biaya layanan Rp '.number_format($fee, 0, ',', '.').')'.($accepted ? '' : ' · PEMBAYARAN GANDA, mohon refund ke customer'),
                );
            }

            return $accepted ? 'paid' : 'refund_needed';
        });

        if ($result === 'paid') {
            $invoice = $payment->invoice()->first();
            SendInvoiceWebhook::for($invoice, 'invoice.paid');
            $this->invoices->email($invoice, 'paid');
        }

        return $result;
    }

    private function open(InvoicePayment $payment): bool
    {
        return in_array($payment->status, [InvoicePayment::STATUS_PENDING, InvoicePayment::STATUS_EXPIRED, InvoicePayment::STATUS_FAILED], true);
    }

    private function uniqueOrderId(): string
    {
        do {
            $id = self::PREFIX.now()->format('ymd').strtoupper(Str::random(10));
        } while (InvoicePayment::where('merchant_order_id', $id)->exists());

        return $id;
    }
}
