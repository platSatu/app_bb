<?php

namespace App\Http\Controllers\Marketplace\Tiktok;

use App\Http\Controllers\Marketplace\StorePublishController;
use App\Models\CatalogProduct;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\Tiktok\TiktokChannel;
use App\Services\Marketplace\Tiktok\TiktokPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Upload produk katalog ke TikTok Shop (Upload Product Image + Create Product).
 * Alur umum di StorePublishController; di sini aturan form khas TikTok, daftar gudang & endpoint brand.
 * View: marketplace.tiktok.publish-form.
 */
class TiktokPublishController extends StorePublishController
{
    protected function channelKey(): string
    {
        return 'tiktok';
    }

    protected function publisher(): ListingPublisher
    {
        return app(TiktokPublisher::class);
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'brand_id' => ['nullable', 'string', 'max:30', 'regex:/^\d+$/'],
            'seller_sku' => ['nullable', 'string', 'max:50'],
            'warehouse_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99999'],
            'price' => ['required', 'integer', 'min:1'],
            'package_weight' => ['required', 'numeric', 'min:0.01', 'max:300'],
            'package_length' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_width' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_height' => ['required', 'numeric', 'min:1', 'max:300'],
            'attributes' => ['nullable', 'array'],
        ];
    }

    protected function attributeNames(): array
    {
        return parent::attributeNames() + ['warehouse_id' => 'gudang'];
    }

    /** Gudang penjualan aktif toko (stok SKU ditulis per gudang). */
    protected function formData(MarketplaceStore $store, CatalogProduct $product): array
    {
        $tiktok = app(TiktokChannel::class);

        try {
            $this->manager->ensureFreshToken($store);

            return ['warehouses' => $tiktok->warehouses($store), 'warehouseError' => null, 'currency' => $tiktok->currency($store)];
        } catch (MarketplaceException $e) {
            return ['warehouses' => [], 'warehouseError' => $e->getMessage(), 'currency' => $tiktok->currency($store)];
        }
    }

    /** Brand untuk kategori, bisa dicari: ?q=nama. Tanpa brand boleh (untuk uji coba). */
    public function brands(Request $request, string $page, string $store, string $category): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, $this->channelKey());
        abort_unless(ctype_digit($category), 404);
        $search = trim((string) $request->query('q')) ?: null;

        return $this->json($item, fn () => app(TiktokChannel::class)->brands($item, $category, $search));
    }
}
