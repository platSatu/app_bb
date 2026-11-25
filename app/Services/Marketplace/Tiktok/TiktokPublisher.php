<?php

namespace App\Services\Marketplace\Tiktok;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Support\Collection;

/**
 * Unggah produk ke TikTok Shop sesuai Product API Solution Guide:
 * 1) Upload Product Image (file) -> uri
 * 2) Create Product (POST /product/202309/products) dengan kategori leaf, atribut produk,
 *    brand (opsional), paket, SKU (harga + stok per gudang) dan external_product_id sebagai kunci idempoten.
 * Setelah dibuat, produk masuk audit TikTok Shop sebelum tampil.
 */
class TiktokPublisher extends ListingPublisher
{
    public function __construct(MarketplaceManager $manager, private readonly TiktokChannel $tiktok)
    {
        parent::__construct($manager);
    }

    protected function send(MarketplaceStore $store, CatalogProduct $product, Collection $images, array $data): array
    {
        $uris = $images->values()->map(fn (CatalogProductImage $image, int $i) => $this->tiktok->uploadImage(
            $store, $this->imageContents($image), 'produk-'.($i + 1).'.'.(pathinfo($image->image, PATHINFO_EXTENSION) ?: 'jpg')
        ))->all();

        $payload = $this->payload($store, $product, $uris, $data);
        $result = $this->tiktok->createProduct($store, $payload);

        if ($result['product_id'] === '') {
            throw new MarketplaceException('TikTok Shop tidak mengembalikan product ID. Cek produk di Seller Center.');
        }

        return ['item_id' => $result['product_id'], 'payload' => $payload, 'response' => $result['raw']];
    }

    public function payload(MarketplaceStore $store, CatalogProduct $product, array $uris, array $data): array
    {
        $description = collect($this->descriptionLines($product))->map(fn ($line) => '<p>'.e($line).'</p>')->implode('') ?: '<p>'.e($product->name).'</p>';

        return array_filter([
            'save_mode' => 'LISTING',
            // Kunci idempoten: request yang diulang tidak membuat produk ganda selama produk lama belum dihapus.
            'external_product_id' => $product->getKey(),
            'title' => mb_substr($product->name, 0, 255),
            'description' => $description,
            'category_id' => (string) $data['category_id'],
            'brand_id' => ! empty($data['brand_id']) ? (string) $data['brand_id'] : null,
            'main_images' => array_map(fn ($uri) => ['uri' => $uri], $uris),
            'package_weight' => ['value' => (string) $data['package_weight'], 'unit' => 'KILOGRAM'],
            'package_dimensions' => [
                'length' => (string) (int) ceil((float) $data['package_length']),
                'width' => (string) (int) ceil((float) $data['package_width']),
                'height' => (string) (int) ceil((float) $data['package_height']),
                'unit' => 'CENTIMETER',
            ],
            'product_attributes' => $this->attributes($data['attributes'] ?? []),
            'skus' => [array_filter([
                'seller_sku' => $data['seller_sku'] ?? null,
                'price' => ['amount' => (string) (int) $data['price'], 'currency' => $this->tiktok->currency($store)],
                'inventory' => [['warehouse_id' => (string) $data['warehouse_id'], 'quantity' => (int) $data['quantity']]],
            ], fn ($v) => $v !== null && $v !== '')],
        ], fn ($v) => $v !== null && $v !== []);
    }

    /** attributes[ID][values][] (id pilihan) + attributes[ID][custom] (isi sendiri) -> product_attributes[]. */
    private function attributes(array $rows): array
    {
        return collect($rows)->map(function ($row, $id) {
            $values = collect((array) ($row['values'] ?? []))->filter(fn ($v) => $v !== '' && $v !== null)->map(fn ($v) => ['id' => (string) $v]);

            if (isset($row['custom']) && trim((string) $row['custom']) !== '') {
                $values->push(['name' => trim((string) $row['custom'])]);
            }

            return $values->isEmpty() ? null : ['id' => (string) $id, 'values' => $values->values()->all()];
        })->filter()->values()->all();
    }
}
