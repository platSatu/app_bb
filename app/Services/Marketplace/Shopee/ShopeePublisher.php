<?php

namespace App\Services\Marketplace\Shopee;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Support\Collection;

/**
 * Unggah produk ke Shopee sesuai panduan "Creating product":
 * 1) v2.media_space.upload_image (file, bukan URL) -> image_id
 * 2) v2.product.add_item dengan kategori leaf, atribut wajib (value_id / value_id 0 + original_value_name),
 *    brand (No Brand = 0), harga, stok (seller_stock), berat, dimensi & logistic_info.
 * Harga coret tidak dikirim: di Shopee diskon diatur lewat promosi (Discount API).
 */
class ShopeePublisher extends ListingPublisher
{
    public function __construct(MarketplaceManager $manager, private readonly ShopeeChannel $shopee)
    {
        parent::__construct($manager);
    }

    protected function send(MarketplaceStore $store, CatalogProduct $product, Collection $images, array $data): array
    {
        // Upload langsung dari file di server, jadi tetap jalan walau bizbos belum bisa diakses publik.
        $imageIds = $images->values()->map(fn (CatalogProductImage $image, int $i) => $this->shopee->uploadImage(
            $store, $this->imageContents($image), 'produk-'.($i + 1).'.'.(pathinfo($image->image, PATHINFO_EXTENSION) ?: 'jpg')
        ))->all();

        $payload = $this->payload($product, $imageIds, $data);
        $result = $this->shopee->addItem($store, $payload);

        if ($result['item_id'] === '') {
            throw new MarketplaceException('Shopee tidak mengembalikan item ID. Cek produk di Seller Center.');
        }

        return ['item_id' => $result['item_id'], 'payload' => $payload, 'response' => $result['raw']];
    }

    public function payload(CatalogProduct $product, array $imageIds, array $data): array
    {
        $description = implode("\n", $this->descriptionLines($product)) ?: $product->name;

        return array_filter([
            'item_name' => mb_substr($product->name, 0, 120),
            'description' => $description,
            'item_sku' => $data['seller_sku'],
            'condition' => 'NEW',
            'item_status' => 'NORMAL',
            'category_id' => (int) $data['category_id'],
            'original_price' => (float) $data['price'],
            'seller_stock' => [['stock' => (int) $data['quantity']]],
            'image' => ['image_id_list' => $imageIds],
            'brand' => [
                'brand_id' => (int) $data['brand_id'],
                'original_brand_name' => (int) $data['brand_id'] === 0 ? 'NoBrand' : (string) ($data['brand_name'] ?? ''),
            ],
            'weight' => (float) $data['package_weight'],
            'dimension' => [
                'package_length' => (int) ceil((float) $data['package_length']),
                'package_width' => (int) ceil((float) $data['package_width']),
                'package_height' => (int) ceil((float) $data['package_height']),
            ],
            'logistic_info' => $this->logistics($data['logistics'] ?? []),
            'attribute_list' => $this->attributes($data['attributes'] ?? []),
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** logistics[ID][enabled|size_id|shipping_fee] -> logistic_info[] (hanya yang dicentang). */
    private function logistics(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->map(fn ($row, $id) => array_filter([
                'logistic_id' => (int) $id,
                'enabled' => true,
                'is_free' => false,
                'size_id' => isset($row['size_id']) && $row['size_id'] !== '' ? (int) $row['size_id'] : null,
                'shipping_fee' => isset($row['shipping_fee']) && $row['shipping_fee'] !== '' ? (float) $row['shipping_fee'] : null,
            ], fn ($v) => $v !== null))
            ->values()->all();
    }

    /**
     * attributes[ID][values][] (value_id terpilih), attributes[ID][custom] (isi sendiri), attributes[ID][unit].
     * Isi sendiri dikirim sebagai value_id 0 + original_value_name (+ value_unit bila atribut bersatuan).
     */
    private function attributes(array $rows): array
    {
        return collect($rows)->map(function ($row, $id) {
            $values = collect((array) ($row['values'] ?? []))->filter(fn ($v) => $v !== '' && $v !== null)
                ->map(fn ($v) => ['value_id' => (int) $v]);

            if (isset($row['custom']) && trim((string) $row['custom']) !== '') {
                $values->push(array_filter([
                    'value_id' => 0,
                    'original_value_name' => trim((string) $row['custom']),
                    'value_unit' => $row['unit'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));
            }

            return $values->isEmpty() ? null : ['attribute_id' => (int) $id, 'attribute_value_list' => $values->values()->all()];
        })->filter()->values()->all();
    }
}
