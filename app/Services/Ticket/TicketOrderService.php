<?php

namespace App\Services\Ticket;

use App\Mail\TicketOrderPaid;
use App\Models\PageCategory;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\UserBranch;
use App\Models\WalletTransaction;
use App\Services\Customer\CustomerService;
use App\Services\Payment\DuitkuClient;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Alur uang tiket: checkout (kuota DITAHAN) -> Duitku -> callback lunas
 * (tahanan jadi terjual, e-ticket dibuat, saldo owner bertambah, email dikirim).
 * Lewat batas bayar = tahanan dilepas. Semua perubahan kuota memakai lockForUpdate.
 */
class TicketOrderService
{
    public const PREFIX = 'TIX';
    private const EXPIRY_GRACE_MINUTES = 10;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly CustomerService $customers,
    ) {
    }

    /**
     * @param  array<string, int>  $quantities  [category_id => qty]
     * @param  array{name: string, email: string, phone: string}  $buyer
     */
    public function create(PageCategory $page, array $quantities, array $buyer, ?string $ip, ?string $userAgent): TicketOrder
    {
        // Penjualan tiket tidak ikut sakelar "Top up aktif"; cukup kredensial Duitku terisi.
        $client = DuitkuClient::make(forCallback: true);
        $quantities = array_filter($quantities, fn ($qty) => $qty > 0);

        if (! $quantities) {
            throw ValidationException::withMessages(['tickets' => 'Pilih minimal 1 tiket dulu ya.']);
        }

        $order = DB::transaction(function () use ($page, $quantities, $buyer, $ip, $userAgent) {
            $branch = UserBranch::whereKey($page->branch_id)->lockForUpdate()->firstOrFail();
            $categories = TicketCategory::where('page_id', $page->getKey())->whereKey(array_keys($quantities))->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($quantities as $id => $qty) {
                $category = $categories->get($id);

                $errors[] = match (true) {
                    ! $category => 'Ada kategori tiket yang sudah tidak tersedia, silakan muat ulang halaman.',
                    (bool) $category->unavailableReason() => "Tiket {$category->name}: {$category->unavailableReason()}.",
                    $qty > $category->max_per_order => "Tiket {$category->name} maksimal {$category->max_per_order} per pembelian.",
                    $qty > $category->remaining() => "Tiket {$category->name} tinggal {$category->remaining()}.",
                    default => null,
                };
            }

            if ($errors = array_filter($errors)) {
                throw ValidationException::withMessages(['tickets' => array_values($errors)]);
            }

            $customer = $this->customers->resolve($branch, $buyer['name'], $buyer['phone'], $buyer['email'], 'Tiket: '.Str::limit($page->name, 200, ''));

            $order = new TicketOrder();
            $order->forceFill([
                'page_id' => $page->getKey(),
                'company_id' => $branch->company_id,
                'customer_id' => $customer->getKey(),
                'reference' => $this->uniqueValue(TicketOrder::class, 'reference', fn () => self::PREFIX.now()->format('ymd').Str::upper(Str::random(6))),
                'access_token' => Str::random(48),
                'name' => $buyer['name'],
                'email' => $buyer['email'],
                'phone' => $buyer['phone'],
                'total' => array_sum(array_map(fn ($id, $qty) => $qty * $categories[$id]->price, array_keys($quantities), $quantities)),
                'status' => TicketOrder::STATUS_PENDING,
                'expires_at' => now()->addMinutes(PaymentGatewaySetting::duitku()->expiry_minutes),
                'ip_address' => $ip,
                'user_agent' => Str::limit((string) $userAgent, 250, ''),
            ])->save();

            foreach ($quantities as $id => $qty) {
                $category = $categories[$id];
                $category->increment('reserved', $qty);

                $item = new TicketOrderItem();
                $item->forceFill([
                    'order_id' => $order->getKey(),
                    'category_id' => $id,
                    'name' => $category->name,
                    'price' => $category->price,
                    'quantity' => $qty,
                    'subtotal' => $category->price * $qty,
                ])->save();
            }

            return $order;
        });

        try {
            $invoice = $client->createInvoice([
                'amount' => $order->total,
                'orderId' => $order->reference,
                'product' => Str::limit('Tiket '.$page->name, 250, ''),
                'customer' => ['name' => $order->name, 'email' => $order->email, 'phone' => $order->phone],
                'items' => $order->items()->get()->map(fn ($item) => ['name' => $item->name, 'price' => $item->price, 'quantity' => $item->quantity])->all(),
                'returnUrl' => route('eticket.order', [$page->slug, $order->access_token]),
            ]);
        } catch (Throwable $e) {
            report($e);
            $invoice = ['paymentUrl' => null, 'reference' => null, 'message' => 'Duitku tidak bisa dihubungi'];
        }

        if (! $invoice['paymentUrl']) {
            $this->close($order, TicketOrder::STATUS_FAILED, $invoice['message'] ?? 'Invoice gagal dibuat');

            throw new RuntimeException('Pembayaran belum bisa dibuat. Tiket Anda tidak jadi ditahan, silakan coba beberapa saat lagi.');
        }

        $order->forceFill(['payment_url' => $invoice['paymentUrl'], 'provider_reference' => $invoice['reference']])->save();

        return $order;
    }

    /** Callback untuk nomor TIX... (sudah dicatat & tanda tangannya valid). */
    public function handleCallback(array $payload, PaymentCallback $log, DuitkuClient $client): array
    {
        $order = TicketOrder::where('reference', $payload['merchantOrderId'])->first();

        if (! $order) {
            $log->update(['result' => 'not_found']);

            return [200, 'OK'];
        }

        if ((int) round((float) $payload['amount']) !== $order->total) {
            $log->update(['result' => 'amount_mismatch']);

            return [200, 'OK'];
        }

        if (($payload['resultCode'] ?? null) !== '00') {
            $log->update(['result' => $this->close($order, TicketOrder::STATUS_FAILED, 'Dibatalkan/gagal di Duitku') ? 'failed' : 'already_processed']);

            return [200, 'OK'];
        }

        if ($this->canBePaid($order) && ($reason = $client->unpaidReason($order->reference, $order->total))) {
            $log->update(['result' => 'not_confirmed', 'message' => $reason]);

            return [409, 'Payment not confirmed'];
        }

        $log->update(['result' => $this->markPaid($order, $payload['paymentCode'] ?? null, $payload['reference'] ?? null)]);

        return [200, 'OK'];
    }

    /** Order pending lewat batas: dicek dulu ke Duitku. Ternyata dibayar = diproses lunas; belum = kuota dilepas. */
    public function expireStale(): array
    {
        $summary = ['paid' => 0, 'expired' => 0, 'skipped' => 0];

        try {
            $client = DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            $client = null;
        }

        TicketOrder::where('status', TicketOrder::STATUS_PENDING)
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->orderBy('expires_at')
            ->chunkById(100, function ($orders) use ($client, &$summary) {
                foreach ($orders as $order) {
                    $paid = $client && $client->unpaidReason($order->reference, $order->total) === null;

                    $result = $paid
                        ? $this->markPaid($order, null, null)
                        : ($this->close($order, TicketOrder::STATUS_EXPIRED, 'Melewati batas waktu pembayaran') ? 'expired' : 'skipped');

                    $summary[in_array($result, ['paid', 'expired'], true) ? $result : 'skipped']++;
                }
            });

        return $summary;
    }

    /** Email gagal tidak boleh menggagalkan callback: tiket tetap bisa dibuka dari halaman order & dikirim ulang owner. */
    public function sendTickets(TicketOrder $order): bool
    {
        try {
            Mail::to($order->email)->queue(new TicketOrderPaid($order->getKey()));
            $order->forceFill(['email_sent_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Satu-satunya jalur order -> lunas: tiket dibuat & saldo owner dikreditkan sekali saja. */
    private function markPaid(TicketOrder $order, ?string $method, ?string $providerReference): string
    {
        $result = DB::transaction(function () use ($order, $method, $providerReference) {
            $locked = TicketOrder::with(['items', 'page.branch.user'])->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canBePaid($locked)) {
                return 'already_processed';
            }

            $wasPending = $locked->isPending();
            $categories = TicketCategory::whereKey($locked->items->pluck('category_id')->filter())->lockForUpdate()->get()->keyBy('id');

            // Dibayar setelah kedaluwarsa: tahanan sudah dilepas, jadi kuota dicek ulang.
            $enoughQuota = $wasPending || $locked->items->every(
                fn ($item) => ($categories->get($item->category_id)?->remaining() ?? 0) >= $item->quantity
            );

            $locked->forceFill([
                'status' => $enoughQuota ? TicketOrder::STATUS_PAID : TicketOrder::STATUS_REFUND_NEEDED,
                'paid_at' => now(),
                'payment_method' => $method ?? $locked->payment_method,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
                'failure_reason' => $enoughQuota ? null : 'Dibayar setelah batas waktu, tetapi kuota tiket sudah habis. Mohon refund ke pembeli.',
            ])->save();

            if ($enoughQuota) {
                $sequence = 0;

                foreach ($locked->items as $item) {
                    $category = $categories->get($item->category_id);

                    if ($category) {
                        $category->forceFill([
                            'reserved' => $wasPending ? max(0, $category->reserved - $item->quantity) : $category->reserved,
                            'sold' => $category->sold + $item->quantity,
                        ])->save();
                    }

                    for ($i = 0; $i < $item->quantity; $i++) {
                        $this->issueTicket($locked, $item, ++$sequence);
                    }
                }
            }

            // Uang sudah diterima Duitku bizbos -> tercatat sebagai saldo owner event.
            $this->wallets->credit(
                $locked->page->branch->user,
                $locked->total,
                WalletTransaction::TYPE_TICKET_SALE,
                'ticket:'.$locked->getKey(),
                $locked,
                'Penjualan tiket '.$locked->reference,
            );

            return $enoughQuota ? 'paid' : 'refund_needed';
        });

        if ($result === 'paid') {
            $this->sendTickets($order->refresh());
        }

        return $result;
    }

    /** Tutup order pending (gagal/kedaluwarsa) dan lepas kuota yang ditahan. */
    private function close(TicketOrder $order, string $status, string $reason): bool
    {
        return DB::transaction(function () use ($order, $status, $reason) {
            $locked = TicketOrder::with('items')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return false;
            }

            $categories = TicketCategory::whereKey($locked->items->pluck('category_id')->filter())->lockForUpdate()->get()->keyBy('id');

            foreach ($locked->items as $item) {
                if ($category = $categories->get($item->category_id)) {
                    $category->forceFill(['reserved' => max(0, $category->reserved - $item->quantity)])->save();
                }
            }

            $locked->forceFill(['status' => $status, 'failure_reason' => $reason])->save();

            return true;
        });
    }

    private function issueTicket(TicketOrder $order, TicketOrderItem $item, int $sequence): void
    {
        $ticket = new Ticket();
        $ticket->forceFill([
            'order_id' => $order->getKey(),
            'page_id' => $order->page_id,
            'category_id' => $item->category_id,
            'category_name' => $item->name,
            'holder_name' => $order->name,
            'booking_code' => $this->uniqueValue(Ticket::class, 'booking_code', fn () => (string) random_int(100000, 999999), ['page_id' => $order->page_id]),
            'number' => $this->uniqueValue(Ticket::class, 'number', fn () => 'BB'.random_int(10000000, 99999999)),
            'token' => Str::random(48),
            'sequence' => $sequence,
        ])->save();
    }

    private function canBePaid(TicketOrder $order): bool
    {
        return in_array($order->status, [TicketOrder::STATUS_PENDING, TicketOrder::STATUS_EXPIRED], true);
    }

    private function uniqueValue(string $model, string $column, callable $make, array $scope = []): string
    {
        do {
            $value = $make();
        } while ($model::where($scope)->where($column, $value)->exists());

        return $value;
    }
}
