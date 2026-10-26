<?php

namespace App\Services\Catalog;

use App\Models\CatalogOrder;
use App\Models\CatalogOrderItem;
use App\Models\CatalogProduct;
use App\Models\PageCategory;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\UserBranch;
use App\Models\WalletTransaction;
use App\Services\Customer\CustomerService;
use App\Services\Payment\DuitkuClient;
use App\Services\Wallet\SettlementCalendar;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Pesanan katalog. Pembayaran (tab Pembayaran di page, settings.catalog.payment):
 * - transfer: pembeli transfer ke rekening owner, owner mengubah status jadi Lunas sendiri.
 * - online: Duitku bizbos -> callback lunas -> saldo owner bertambah (sama seperti penjualan tiket).
 * Stok dipotong saat checkout; pesanan online yang lewat batas bayar dibatalkan & stoknya dikembalikan.
 */
class CatalogOrderService
{
    public const PREFIX = 'ORD';
    public const MAX_LINES = 50;
    public const MAX_QTY = 999;
    public const MAX_BANKS = 5;
    private const EXPIRY_GRACE_MINUTES = 10;

    public function __construct(
        private readonly CustomerService $customers,
        private readonly WalletService $wallets,
    ) {
    }

    /** Metode bayar yang aktif di page ini. Kosong = pesanan dikonfirmasi langsung oleh penjual (alur lama). */
    public static function paymentMethods(PageCategory $page): array
    {
        $gateway = PaymentGatewaySetting::duitku();

        return array_values(array_filter([
            $page->setting('catalog.payment.transfer') && $page->setting('catalog.payment.banks') ? CatalogOrder::PAYMENT_TRANSFER : null,
            // Penjualan tidak ikut sakelar "Top up aktif"; cukup kredensial Duitku terisi (sama seperti tiket).
            $page->setting('catalog.payment.online') && filled($gateway->merchantCode()) && filled($gateway->apiKey()) ? CatalogOrder::PAYMENT_ONLINE : null,
        ]));
    }

    /**
     * Buat pesanan. Harga & stok SELALU diambil dari database (bukan dari browser),
     * produk dikunci supaya 2 checkout bersamaan tidak membuat stok minus.
     *
     * @param  array<string, int>  $cart  [product_id => qty]
     * @param  array{name: string, phone: string, email: ?string, address: ?string, note: ?string}  $buyer
     */
    public function place(PageCategory $page, array $cart, array $buyer, ?string $paymentType, ?string $ip, ?string $userAgent): CatalogOrder
    {
        return DB::transaction(function () use ($page, $cart, $buyer, $paymentType, $ip, $userAgent) {
            $branch = UserBranch::whereKey($page->branch_id)->lockForUpdate()->firstOrFail();

            $products = CatalogProduct::where('page_id', $page->getKey())
                ->active()
                ->whereKey(array_keys($cart))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            $lines = [];

            foreach ($cart as $productId => $qty) {
                $product = $products->get($productId);

                if (! $product) {
                    $errors[] = 'Ada produk yang sudah tidak tersedia, silakan muat ulang keranjang.';
                    continue;
                }

                if ($product->tracksStock() && $product->stock < $qty) {
                    $errors[] = $product->stock > 0
                        ? "Stok {$product->name} tinggal {$product->stock}."
                        : "{$product->name} sudah habis.";
                    continue;
                }

                $lines[] = [$product, $qty, $product->finalPrice()];
            }

            if ($errors || ! $lines) {
                throw ValidationException::withMessages(['cart' => $errors ?: ['Keranjang masih kosong.']]);
            }

            $customer = $this->customers->resolve($branch, 'pages', $buyer['name'], $buyer['phone'], $buyer['email'], 'Katalog: '.Str::limit($page->name, 200, ''));

            $order = new CatalogOrder();
            $order->forceFill([
                'page_id' => $page->getKey(),
                'company_id' => $branch->company_id,
                'customer_id' => $customer->getKey(),
                'reference' => $this->uniqueValue('reference', fn () => self::PREFIX.now()->format('ymd').Str::upper(Str::random(5))),
                'access_token' => $this->uniqueValue('access_token', fn () => Str::random(48)),
                'name' => $buyer['name'],
                'phone' => $buyer['phone'],
                'email' => $buyer['email'],
                'address' => $buyer['address'],
                'note' => $buyer['note'],
                'total' => array_sum(array_map(fn ($line) => $line[1] * $line[2], $lines)),
                'payment_type' => $paymentType,
                'expires_at' => $paymentType === CatalogOrder::PAYMENT_ONLINE ? now()->addMinutes(PaymentGatewaySetting::duitku()->expiry_minutes) : null,
                'status' => CatalogOrder::STATUS_NEW,
                'ip_address' => $ip,
                'user_agent' => Str::limit((string) $userAgent, 250, ''),
            ])->save();

            foreach ($lines as [$product, $qty, $price]) {
                $item = new CatalogOrderItem();
                $item->forceFill([
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'name' => $product->name,
                    'price' => $price,
                    'quantity' => $qty,
                    'subtotal' => $price * $qty,
                ])->save();

                if ($product->tracksStock()) {
                    $product->decrement('stock', $qty);
                }
            }

            return $order;
        });
    }

    /** Buat tagihan Duitku untuk pesanan online. Gagal = pesanan dibatalkan & stok kembali. @return string link bayar */
    public function startPayment(CatalogOrder $order, PageCategory $page): string
    {
        try {
            $invoice = DuitkuClient::make(forCallback: true)->createInvoice([
                'amount' => $order->total,
                'orderId' => $order->reference,
                'product' => Str::limit('Pesanan '.$page->name, 250, ''),
                'customer' => ['name' => $order->name, 'email' => (string) $order->email, 'phone' => $order->phone],
                // Duitku: price per baris = harga x jumlah, dan totalnya harus sama dengan paymentAmount.
                'items' => $order->items()->get()->map(fn ($item) => ['name' => Str::limit($item->name, 50, ''), 'price' => $item->subtotal, 'quantity' => $item->quantity])->all(),
                'returnUrl' => route('page.order', [$page->slug, $order->access_token]),
            ]);
        } catch (Throwable $e) {
            report($e);
            $invoice = ['paymentUrl' => null, 'reference' => null, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Duitku tidak bisa dihubungi'];
        }

        if (! $invoice['paymentUrl']) {
            $this->closeUnpaid($order, $invoice['message'] ?? 'Tagihan pembayaran gagal dibuat');

            throw new RuntimeException('Pembayaran belum bisa dibuat. Pesanan dibatalkan dan stok dikembalikan, silakan coba beberapa saat lagi.');
        }

        $order->forceFill(['payment_url' => $invoice['paymentUrl'], 'provider_reference' => $invoice['reference']])->save();

        return $invoice['paymentUrl'];
    }

    /** Callback Duitku untuk nomor ORD... (sudah dicatat & tanda tangannya valid). */
    public function handleCallback(array $payload, PaymentCallback $log, DuitkuClient $client): array
    {
        $order = CatalogOrder::where('reference', $payload['merchantOrderId'])->where('payment_type', CatalogOrder::PAYMENT_ONLINE)->first();

        if (! $order) {
            $log->update(['result' => 'not_found']);

            return [200, 'OK'];
        }

        if ((int) round((float) $payload['amount']) !== $order->total) {
            $log->update(['result' => 'amount_mismatch']);

            return [200, 'OK'];
        }

        if (($payload['resultCode'] ?? null) !== '00') {
            $log->update(['result' => $this->closeUnpaid($order, 'Dibatalkan/gagal di Duitku') ? 'failed' : 'already_processed']);

            return [200, 'OK'];
        }

        if ($order->awaitsOnlinePayment() && ($reason = $client->unpaidReason($order->reference, $order->total))) {
            $log->update(['result' => 'not_confirmed', 'message' => $reason]);

            return [409, 'Payment not confirmed'];
        }

        $log->update(['result' => $this->markPaid($order, $payload['paymentCode'] ?? null, $payload['reference'] ?? null)]);

        return [200, 'OK'];
    }

    /** Pesanan online lewat batas bayar: dicek dulu ke Duitku. Ternyata dibayar = lunas; belum = dibatalkan & stok kembali. */
    public function expireStale(): array
    {
        $summary = ['paid' => 0, 'expired' => 0, 'skipped' => 0];

        try {
            $client = DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            $client = null;
        }

        CatalogOrder::where('payment_type', CatalogOrder::PAYMENT_ONLINE)
            ->where('status', CatalogOrder::STATUS_NEW)
            ->whereNull('paid_at')
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->orderBy('expires_at')
            ->chunkById(100, function ($orders) use ($client, &$summary) {
                foreach ($orders as $order) {
                    $paid = $client && $client->unpaidReason($order->reference, $order->total) === null;

                    $result = $paid
                        ? $this->markPaid($order, null, null)
                        : ($this->closeUnpaid($order, 'Melewati batas waktu pembayaran') ? 'expired' : 'skipped');

                    $summary[in_array($result, ['paid', 'expired'], true) ? $result : 'skipped']++;
                }
            });

        return $summary;
    }

    /** Ubah status oleh owner. Dibatalkan = stok dikembalikan, hanya sekali (stock_restored). */
    public function changeStatus(CatalogOrder $order, string $status): bool
    {
        return DB::transaction(function () use ($order, $status) {
            $locked = CatalogOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isFinal() || $locked->status === $status) {
                return false;
            }

            if ($status === CatalogOrder::STATUS_CANCELLED) {
                $this->restoreStock($locked);
            }

            $locked->status = $status;
            $locked->save();

            return true;
        });
    }

    /**
     * Satu-satunya jalur uang online -> saldo owner. paid_at dikunci & diisi sekali, kredit saldo memakai
     * idempotency key, jadi callback ganda / job kedaluwarsa yang bersamaan tidak menambah saldo dua kali.
     */
    private function markPaid(CatalogOrder $order, ?string $method, ?string $providerReference): string
    {
        return DB::transaction(function () use ($order, $method, $providerReference) {
            $locked = CatalogOrder::with('page.branch.user')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->awaitsOnlinePayment()) {
                return 'already_processed';
            }

            // Sudah dibatalkan (kedaluwarsa) lalu ternyata dibayar: stok diambil lagi kalau masih cukup.
            $note = $locked->stock_restored
                ? ($this->retakeStock($locked)
                    ? 'Dibayar setelah batas waktu; stok sudah diambil kembali.'
                    : 'Dibayar setelah batas waktu, tetapi stok tidak cukup. Mohon hubungi pembeli (kirim sebagian / refund).')
                : null;

            $locked->forceFill([
                // Status yang sudah dilanjutkan owner (Diproses/Selesai) tidak dimundurkan.
                'status' => in_array($locked->status, [CatalogOrder::STATUS_NEW, CatalogOrder::STATUS_CANCELLED], true) ? CatalogOrder::STATUS_PAID : $locked->status,
                'paid_at' => now(),
                'payment_method' => $method ? strtoupper(substr($method, 0, 20)) : $locked->payment_method,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
                'payment_note' => $note,
            ])->save();

            // Uang diterima Duitku bizbos -> saldo owner toko (sama seperti penjualan tiket).
            $this->wallets->credit(
                $locked->page->branch->user,
                $locked->total,
                WalletTransaction::TYPE_CATALOG_SALE,
                'catalog:'.$locked->getKey(),
                $locked,
                'Penjualan katalog '.$locked->reference,
                paymentMethod: $locked->payment_method,
                availableAt: SettlementCalendar::availableAt($locked->payment_method),
            );

            return 'paid';
        });
    }

    /** Batalkan pesanan online yang belum dibayar & kembalikan stok. False = sudah dibayar / sudah diproses. */
    private function closeUnpaid(CatalogOrder $order, string $reason): bool
    {
        return DB::transaction(function () use ($order, $reason) {
            $locked = CatalogOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->awaitsOnlinePayment() || $locked->status !== CatalogOrder::STATUS_NEW) {
                return false;
            }

            $this->restoreStock($locked);
            $locked->forceFill(['status' => CatalogOrder::STATUS_CANCELLED, 'payment_note' => Str::limit($reason, 250, '')])->save();

            return true;
        });
    }

    /** Kembalikan stok sekali saja. Harus dipanggil di dalam transaksi dengan pesanan sudah terkunci. */
    private function restoreStock(CatalogOrder $locked): void
    {
        if ($locked->stock_restored) {
            return;
        }

        $items = $locked->items()->whereNotNull('product_id')->get();
        $products = CatalogProduct::whereKey($items->pluck('product_id'))->whereNotNull('stock')->lockForUpdate()->get()->keyBy('id');

        foreach ($items as $item) {
            $products->get($item->product_id)?->increment('stock', $item->quantity);
        }

        $locked->stock_restored = true;
    }

    /** Ambil lagi stok yang sudah dikembalikan. Semua atau tidak sama sekali. Harus di dalam transaksi. */
    private function retakeStock(CatalogOrder $locked): bool
    {
        $items = $locked->items()->whereNotNull('product_id')->get();
        $products = CatalogProduct::whereKey($items->pluck('product_id'))->whereNotNull('stock')->lockForUpdate()->get()->keyBy('id');

        if ($items->contains(fn ($item) => ($product = $products->get($item->product_id)) && $product->stock < $item->quantity)) {
            return false;
        }

        foreach ($items as $item) {
            $products->get($item->product_id)?->decrement('stock', $item->quantity);
        }

        $locked->stock_restored = false;

        return true;
    }

    private function uniqueValue(string $column, callable $make): string
    {
        do {
            $value = $make();
        } while (CatalogOrder::where($column, $value)->exists());

        return $value;
    }
}
