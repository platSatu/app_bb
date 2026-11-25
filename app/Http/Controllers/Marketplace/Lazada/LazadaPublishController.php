<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Marketplace\StorePublishController;
use App\Services\Marketplace\Lazada\LazadaPublisher;
use App\Services\Marketplace\ListingPublisher;

/**
 * Upload produk katalog ke Lazada (CreateProduct). Alur umum di StorePublishController;
 * di sini hanya aturan form khas Lazada. View: marketplace.lazada.publish-form.
 */
class LazadaPublishController extends StorePublishController
{
    protected function channelKey(): string
    {
        return 'lazada';
    }

    protected function publisher(): ListingPublisher
    {
        return app(LazadaPublisher::class);
    }

    protected function rules(): array
    {
        return [
            'category_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'brand_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'seller_sku' => ['required', 'string', 'max:100', 'not_regex:/[\^~<>|"]/'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['required', 'integer', 'min:1000'],
            'special_price' => ['nullable', 'integer', 'min:1000', 'lt:price'],
            'package_weight' => ['required', 'numeric', 'min:0.01', 'max:300'],
            'package_length' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_width' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_height' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_content' => ['nullable', 'string', 'max:200'],
            'attributes' => ['nullable', 'array'],
            'sku_attributes' => ['nullable', 'array'],
        ];
    }

    /** Sembunyikan atribut yang sudah punya kolom form; variasi (sale prop) opsional belum didukung. */
    protected function filterAttributes(array $attributes): array
    {
        $hidden = [...LazadaPublisher::PRODUCT_FIELDS, ...LazadaPublisher::SKU_FIELDS];

        return collect($attributes)
            ->reject(fn ($a) => in_array($a['name'], $hidden, true))
            ->reject(fn ($a) => $a['is_sale_prop'] && ! $a['is_mandatory'])
            ->sortByDesc('is_mandatory')
            ->values()->all();
    }
}
