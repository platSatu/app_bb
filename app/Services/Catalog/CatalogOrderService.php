<?php

namespace App\Services\Catalog;

use App\Models\CatalogOrder;
use App\Models\CatalogOrderItem;
use App\Models\CatalogProduct;
use App\Models\PageCategory;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogOrderService
{
    public const MAX_LINES = 50;
    public const MAX_QTY = 999;

    public function __construct(private readonly CustomerService $customers)
    {
    }

    /**
     * Buat pesanan. Harga & stok SELALU diambil dari database (bukan dari browser),
     * produk dikunci supaya 2 checkout bersamaan tidak membuat stok minus.
     *
     * @param  array<string, int>  $cart  [product_id => qty]
     * @param  array{name: string, phone: string, email: ?string, address: ?string, note: ?string}  $buyer
     */
    public function place(PageCategory $page, array $cart, array $buyer, ?string $ip, ?string $userAgent): CatalogOrder
    {
        return DB::transaction(function () use ($page, $cart, $buyer, $ip, $userAgent) {
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

            $customer = $this->customers->resolve($branch, $buyer['name'], $buyer['phone'], $buyer['email'], 'Katalog: '.Str::limit($page->name, 200, ''));

            $order = new CatalogOrder();
            $order->forceFill([
                'page_id' => $page->getKey(),
                'company_id' => $branch->company_id,
                'customer_id' => $customer->getKey(),
                'reference' => $this->uniqueReference(),
                'name' => $buyer['name'],
                'phone' => $buyer['phone'],
                'email' => $buyer['email'],
                'address' => $buyer['address'],
                'note' => $buyer['note'],
                'total' => array_sum(array_map(fn ($line) => $line[1] * $line[2], $lines)),
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

    /** Ubah status. Dibatalkan = stok dikembalikan, hanya sekali (stock_restored). */
    public function changeStatus(CatalogOrder $order, string $status): bool
    {
        return DB::transaction(function () use ($order, $status) {
            $locked = CatalogOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isFinal() || $locked->status === $status) {
                return false;
            }

            if ($status === CatalogOrder::STATUS_CANCELLED && ! $locked->stock_restored) {
                $items = $locked->items()->whereNotNull('product_id')->get();
                $products = CatalogProduct::whereKey($items->pluck('product_id'))->whereNotNull('stock')->lockForUpdate()->get()->keyBy('id');

                foreach ($items as $item) {
                    $products->get($item->product_id)?->increment('stock', $item->quantity);
                }

                $locked->stock_restored = true;
            }

            $locked->status = $status;
            $locked->save();

            return true;
        });
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'ORD'.now()->format('ymd').Str::upper(Str::random(5));
        } while (CatalogOrder::where('reference', $reference)->exists());

        return $reference;
    }
}
