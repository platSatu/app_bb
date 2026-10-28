<?php

namespace App\Services\Ticket;

use App\Mail\TicketOrderPaid;
use App\Models\PageCategory;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketEmailLog;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketSetting;
use App\Models\TicketStockLog;
use App\Models\UserBranch;
use App\Models\WalletTransaction;
use App\Services\Customer\CustomerService;
use App\Services\Page\PageSubmissionService;
use App\Services\Payment\DuitkuClient;
use App\Services\Wallet\SettlementCalendar;
use App\Services\Wallet\WalletService;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Alur uang tiket: checkout (kuota DITAHAN) -> Duitku -> callback lunas
 * (tahanan jadi terjual, e-ticket dibuat, saldo owner bertambah, email dikirim).
 * Biaya layanan bizbos dihitung & DIKUNCI saat checkout (subtotal, fee, fee_percent, fee_payer):
 * pembeli membayar `total` (subtotal + fee kalau pembeli yang menanggung); saldo penyelenggara
 * bertambah `total - fee`; fee tetap di bizbos (tidak masuk saldo siapa pun).
 * Lewat batas bayar = tahanan dilepas. Kuota hanya berubah lewat UPDATE bersyarat / lockForUpdate,
 * jadi aman walau ribuan checkout terjadi bersamaan (war ticket).
 */
class TicketOrderService
{
    use DetectsConcurrencyErrors;

    public const PREFIX = 'TIX';
    private const EXPIRY_GRACE_MINUTES = 10;
    private const TRANSACTION_ATTEMPTS = 3;
    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly CustomerService $customers,
        private readonly PageSubmissionService $submissions,
    ) {
    }

    /**
     * @param  array<string, int>  $quantities  [category_id => qty]
     * @param  array{name: string, email: string, phone: string}  $buyer
     * @param  array  $answers  jawaban Form Pembeli yang sudah tervalidasi
     */
    public function create(PageCategory $page, array $quantities, array $buyer, array $answers, ?string $ip, ?string $userAgent): TicketOrder
    {
        $quantities = array_filter($quantities, fn ($qty) => $qty > 0);

        if (! $quantities) {
            throw ValidationException::withMessages(['tickets' => 'Pilih minimal 1 tiket dulu ya.']);
        }

        // Tolak cepat TANPA kunci: saat war ticket, pembeli yang datang setelah tiket habis
        // langsung dapat jawaban, tidak ikut mengantre kunci database.
        $this->assertAvailable(TicketCategory::where('page_id', $page->getKey())->whereKey(array_keys($quantities))->get()->keyBy('id'), $quantities);

        $this->tuneConnection(true);

        try {
            $order = $this->withRetry(fn () => DB::transaction(
                fn () => $this->reserve($page, $quantities, $buyer, $answers, $ip, $userAgent),
                self::TRANSACTION_ATTEMPTS,
            ));
        } finally {
            $this->tuneConnection(false);
        }

        if ($order->total === 0) {
            $this->markPaid($order, 'free', null);

            return $order->refresh();
        }

        try {
            // Penjualan tiket tidak ikut sakelar "Top up aktif"; cukup kredensial Duitku terisi.
            $invoice = DuitkuClient::make(forCallback: true)->createInvoice([
                'amount' => $order->total,
                'orderId' => $order->reference,
                'product' => Str::limit('Tiket '.$page->name, 250, ''),
                'customer' => ['name' => $order->name, 'email' => $order->email, 'phone' => $order->phone],
                // Duitku: price per baris = harga x jumlah, dan totalnya harus sama dengan paymentAmount.
                'items' => $order->items()->get()->map(fn ($item) => ['name' => $item->name, 'price' => $item->subtotal, 'quantity' => $item->quantity])
                    ->when($order->buyerPaysFee() && $order->fee > 0, fn ($items) => $items->push(['name' => 'Biaya layanan', 'price' => $order->fee, 'quantity' => 1]))
                    ->all(),
                'returnUrl' => route('eticket.order', [$page->slug, $order->access_token]),
            ]);
        } catch (Throwable $e) {
            report($e);
            $invoice = ['paymentUrl' => null, 'reference' => null, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Duitku tidak bisa dihubungi'];
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

    /**
     * Email gagal tidak boleh menggagalkan callback: tiket tetap bisa dibuka dari halaman order & dikirim ulang owner.
     * Setiap pengiriman dicatat; statusnya diperbarui oleh TicketOrderPaid saat benar-benar terkirim/gagal.
     */
    public function sendTickets(TicketOrder $order, string $trigger = 'auto', ?string $actorId = null): bool
    {
        $log = new TicketEmailLog();
        $log->forceFill([
            'page_id' => $order->page_id,
            'order_id' => $order->getKey(),
            'actor_id' => $actorId,
            'email' => $order->email,
            'trigger' => $trigger,
            'status' => TicketEmailLog::STATUS_QUEUED,
        ])->save();

        try {
            Mail::to($order->email)->queue(new TicketOrderPaid($order->getKey(), $log->getKey()));
            $order->forceFill(['email_sent_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            report($e);
            TicketOrderPaid::markFailed($log->getKey(), $e);

            return false;
        }
    }

    /**
     * Buat pesanan + tahan kuota dalam 1 transaksi.
     * Urutan sengaja: data pembeli & pesanan disiapkan dulu, kuota dipotong PALING AKHIR dengan 1 UPDATE
     * bersyarat (sisa >= jumlah). Baris kategori hanya terkunci beberapa milidetik sampai commit,
     * jadi ribuan checkout bersamaan tetap antre cepat dan kuota mustahil terjual lebih.
     */
    private function reserve(PageCategory $page, array $quantities, array $buyer, array $answers, ?string $ip, ?string $userAgent): TicketOrder
    {
        // Tidak mengunci baris branch: kontak kembar sudah dicegah unique index (branch_id + phone/email),
        // tabrakan langka ditangani withRetry().
        $branch = UserBranch::whereKey($page->branch_id)->firstOrFail();
        $categories = TicketCategory::where('page_id', $page->getKey())->whereKey(array_keys($quantities))->get()->keyBy('id');
        $this->assertAvailable($categories, $quantities);

        $customer = $this->customers->resolve($branch, 'events', $buyer['name'], $buyer['phone'], $buyer['email'], 'Tiket: '.Str::limit($page->name, 200, ''));
        $subtotal = array_sum(array_map(fn ($id, $qty) => $qty * $categories[$id]->price, array_keys($quantities), $quantities));
        $feePayer = $page->ticketFeePayer();

        // Event gratis tidak boleh menagih apa pun (penjagaan kedua; kategori sudah dipaksa Rp0 saat disimpan).
        if ($feePayer === TicketSetting::PAYER_FREE && $subtotal > 0) {
            throw ValidationException::withMessages(['tickets' => 'Tiket ini sedang diperbarui penyelenggara. Silakan muat ulang halaman ya.']);
        }

        $feePercent = TicketSetting::current()->fee_percent;
        $fee = TicketSetting::feeFor($subtotal, $feePercent);
        $total = $feePayer === TicketSetting::PAYER_BUYER ? $subtotal + $fee : $subtotal;

        // Tiket gratis: 1 orang (email/WA yang sama = customer yang sama) cukup sekali per page, supaya tidak diborong.
        // Baris customer sudah terkunci oleh resolve(), jadi 2 klik bersamaan dari orang yang sama antre di sini,
        // dan (READ COMMITTED) yang kedua melihat pesanan yang pertama.
        if ($total === 0 && TicketOrder::where('page_id', $page->getKey())->where('customer_id', $customer->getKey())
            ->where('total', 0)->whereIn('status', [TicketOrder::STATUS_PENDING, TicketOrder::STATUS_PAID])->exists()) {
            throw ValidationException::withMessages(['tickets' => 'Email atau nomor WhatsApp ini sudah pernah mendaftar tiket gratis di acara ini.']);
        }

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
            'subtotal' => $subtotal,
            'fee' => $fee,
            'fee_percent' => $feePercent,
            'fee_payer' => $feePayer,
            'total' => $total,
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(PaymentGatewaySetting::duitku()->expiry_minutes),
            // Checkout sudah menolak pesanan tanpa centang S&K, jadi di sini cukup dicatat waktunya.
            'terms_accepted_at' => filled($page->setting('ticket.terms')) ? now() : null,
            'ip_address' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
        ])->save();

        foreach ($quantities as $id => $qty) {
            $category = $categories[$id];
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

        $submission = $page->fields->isNotEmpty() ? $this->submissions->submit($page, $customer, $answers, $ip, $userAgent) : null;

        if ($submission) {
            $order->forceFill(['submission_id' => $submission->getKey()])->save();
        }

        try {
            // Urut id: 2 pesanan berisi kategori yang sama selalu mengunci dengan urutan sama (tidak deadlock).
            $ids = array_keys($quantities);
            sort($ids);

            foreach ($ids as $id) {
                $this->holdQuota($categories[$id], $quantities[$id], $order);
            }
        } catch (Throwable $e) {
            // File Form Pembeli sudah tersimpan di disk; transaksi dibatalkan, jadi file-nya ikut dibuang.
            if ($submission) {
                Storage::disk(PageSubmissionService::DISK)->deleteDirectory(PageSubmissionService::directory($page).'/'.$submission->getKey());
            }

            throw $e;
        }

        return $order;
    }

    /**
     * Potong kuota secara atomik. Harga & jadwal jual ikut dicek di UPDATE yang sama,
     * jadi pesanan tidak bisa lolos dengan harga lama atau setelah penjualan ditutup.
     */
    private function holdQuota(TicketCategory $category, int $qty, TicketOrder $order): void
    {
        $now = now();
        $held = TicketCategory::whereKey($category->getKey())
            ->where('status', TicketCategory::STATUS_ACTIVE)
            ->where('price', $category->price)
            ->where(fn ($q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $now))
            ->whereRaw('quota - sold - reserved >= ?', [$qty])
            ->update(['reserved' => DB::raw('reserved + '.(int) $qty), 'updated_at' => $now]);

        // Baca ulang dengan kunci (kita sudah memegang kuncinya kalau berhasil), untuk riwayat stok / alasan gagal.
        $fresh = TicketCategory::whereKey($category->getKey())->lockForUpdate()->firstOrFail();

        if ($held !== 1) {
            $reason = $fresh->unavailableReason();

            throw ValidationException::withMessages(['tickets' => match (true) {
                (bool) $reason => "Tiket {$fresh->name}: {$reason}.",
                $fresh->price !== $category->price => "Harga tiket {$fresh->name} baru saja berubah. Silakan muat ulang halaman ya.",
                default => "Yah, tiket {$fresh->name} baru saja diambil pembeli lain dan tinggal {$fresh->remaining()}. Coba kurangi jumlahnya ya.",
            }]);
        }

        $before = TicketStockLog::snapshot($fresh);
        $before['reserved'] -= $qty;
        TicketStockLog::record($fresh, $before, TicketStockLog::ACTION_RESERVE, $order->getKey());
    }

    /** @param  \Illuminate\Support\Collection<string, TicketCategory>  $categories */
    private function assertAvailable($categories, array $quantities): void
    {
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
    }

    /**
     * Deadlock / antre kunci terlalu lama dicoba ulang oleh DB::transaction. Kontak kembar
     * (2 checkout pertama dari orang yang sama di detik yang sama) dicoba ulang di sini:
     * percobaan berikutnya akan menemukan kontak yang sudah tersimpan.
     */
    private function withRetry(callable $callback): TicketOrder
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::TRANSACTION_ATTEMPTS) {
                    throw $this->busy($e);
                }
            } catch (QueryException $e) {
                throw $this->causedByConcurrencyError($e) ? $this->busy($e) : $e;
            }
        }
    }

    private function busy(Throwable $e): RuntimeException
    {
        report($e);

        return new RuntimeException('Wah, sedang banyak sekali yang membeli bersamaan. Tiket Anda belum tertahan, silakan tekan tombol beli sekali lagi ya.');
    }

    /**
     * MySQL, khusus selama checkout:
     * - tidak menunggu kunci sampai 50 detik (default); lebih baik cepat gagal lalu dicoba ulang,
     * - READ COMMITTED: tanpa gap lock (sumber deadlock saat ribuan insert bersamaan) dan
     *   setiap baca melihat data terbaru yang sudah di-commit.
     */
    private function tuneConnection(bool $checkout): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('SET SESSION innodb_lock_wait_timeout = '.($checkout ? self::LOCK_WAIT_SECONDS : 'DEFAULT'));
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.($checkout ? 'READ COMMITTED' : 'REPEATABLE READ'));
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
                        $before = TicketStockLog::snapshot($category);
                        $category->forceFill([
                            'reserved' => $wasPending ? max(0, $category->reserved - $item->quantity) : $category->reserved,
                            'sold' => $category->sold + $item->quantity,
                        ])->save();
                        TicketStockLog::record($category, $before, TicketStockLog::ACTION_SELL, $locked->getKey(), null,
                            $locked->total === 0 ? 'Tiket gratis' : ($wasPending ? null : 'Dibayar setelah batas waktu'));
                    }

                    for ($i = 0; $i < $item->quantity; $i++) {
                        $this->issueTicket($locked, $item, ++$sequence);
                    }
                }
            }

            // Uang sudah diterima Duitku bizbos -> saldo owner event = dibayar pembeli dikurangi biaya layanan
            // (angka yang dikunci saat checkout). Biaya layanan tetap di bizbos. Tiket gratis tidak ada uang masuk.
            if ($locked->netAmount() > 0) {
                $this->wallets->credit(
                    $locked->page->branch->user,
                    $locked->netAmount(),
                    WalletTransaction::TYPE_TICKET_SALE,
                    'ticket:'.$locked->getKey(),
                    $locked,
                    'Penjualan tiket '.$locked->reference.($locked->fee > 0 ? ' (dikurangi biaya layanan Rp '.number_format($locked->fee, 0, ',', '.').')' : ''),
                    paymentMethod: $locked->payment_method,
                    availableAt: SettlementCalendar::availableAt($locked->payment_method),
                );
            }

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
                    $before = TicketStockLog::snapshot($category);
                    $category->forceFill(['reserved' => max(0, $category->reserved - $item->quantity)])->save();
                    TicketStockLog::record($category, $before, TicketStockLog::ACTION_RELEASE, $locked->getKey(), null, $reason);
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

    /** Pending, atau kedaluwarsa/gagal tapi ternyata dibayar (dicek ulang ke Duitku; kuota dicek ulang di markPaid). */
    private function canBePaid(TicketOrder $order): bool
    {
        return in_array($order->status, [TicketOrder::STATUS_PENDING, TicketOrder::STATUS_EXPIRED, TicketOrder::STATUS_FAILED], true);
    }

    private function uniqueValue(string $model, string $column, callable $make, array $scope = []): string
    {
        do {
            $value = $make();
        } while ($model::where($scope)->where($column, $value)->exists());

        return $value;
    }
}
