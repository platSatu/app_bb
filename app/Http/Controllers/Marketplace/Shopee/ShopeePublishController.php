<?php

namespace App\Http\Controllers\Marketplace\Shopee;

use App\Http\Controllers\Marketplace\StorePublishController;
use App\Models\CatalogProduct;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\Shopee\ShopeeChannel;
use App\Services\Marketplace\Shopee\ShopeePublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Upload produk katalog ke Shopee (upload_image + add_item). Alur umum di StorePublishController;
 * di sini aturan form khas Shopee, daftar jasa kirim & endpoint brand per kategori.
 * View: marketplace.shopee.publish-form.
 */
class ShopeePublishController extends StorePublishController
{
    protected function channelKey(): string
    {
        return 'shopee';
    }

    protected function publisher(): ListingPublisher
    {
        return app(ShopeePublisher::class);
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            // 0 = No Brand.
            'brand_id' => ['required', 'integer', 'min:0'],
            'brand_name' => ['nullable', 'string', 'max:100', 'required_unless:brand_id,0'],
            'seller_sku' => ['nullable', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['required', 'integer', 'min:100'],
            'package_weight' => ['required', 'numeric', 'min:0.01', 'max:300'],
            'package_length' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_width' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_height' => ['required', 'numeric', 'min:1', 'max:300'],
            'logistics' => ['required', 'array'],
            'logistics.*.enabled' => ['nullable', 'boolean'],
            'logistics.*.size_id' => ['nullable', 'integer'],
            'logistics.*.shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array'],
        ];
    }

    protected function attributeNames(): array
    {
        return parent::attributeNames() + ['brand_name' => 'nama brand', 'logistics' => 'jasa kirim'];
    }

    /** Jasa kirim aktif toko ini (wajib dipilih minimal satu saat add_item). */
    protected function formData(MarketplaceStore $store, CatalogProduct $product): array
    {
        try {
            $this->manager->ensureFreshToken($store);

            return ['logistics' => app(ShopeeChannel::class)->logisticChannels($store), 'logisticsError' => null];
        } catch (MarketplaceException $e) {
            return ['logistics' => [], 'logisticsError' => $e->getMessage()];
        }
    }

    /** Brand yang boleh dipakai kategori (No Brand = 0 selalu tersedia). */
    public function brands(Request $request, string $page, string $store, string $category): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, $this->channelKey());
        abort_unless(ctype_digit($category), 404);

        return $this->json($item, fn () => app(ShopeeChannel::class)->brands($item, $category));
    }

    public function store(Request $request, string $page, string $store, string $product): RedirectResponse
    {
        // Minimal satu jasa kirim dicentang.
        $request->validate(['logistics' => [function ($attr, $value, $fail) {
            if (! collect((array) $value)->contains(fn ($row) => ! empty($row['enabled']))) {
                $fail('Pilih minimal satu jasa kirim.');
            }
        }]]);

        return parent::store($request, $page, $store, $product);
    }
}
