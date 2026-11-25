<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Support\Collection;

/**
 * Unggah produk ke Lazada: MigrateImage (URL foto publik -> server Lazada) lalu CreateProduct (payload JSON).
 * Dokumentasi: https://open.lazada.com/apps/doc/doc?nodeId=30720&docId=120949
 */
class LazadaPublisher extends ListingPublisher
{
    // Atribut SKU yang sudah punya kolom form sendiri.
    public const SKU_FIELDS = ['SellerSku', 'quantity', 'price', 'special_price', 'special_from_date', 'special_to_date',
        'package_weight', 'package_length', 'package_width', 'package_height', 'package_content', '__images__', 'color_thumbnail'];

    // Atribut produk yang sudah punya kolom form sendiri.
    public const PRODUCT_FIELDS = ['name', 'description', 'short_description', 'brand', 'brand_id'];

    public function __construct(MarketplaceManager $manager, private readonly LazadaChannel $lazada)
    {
        parent::__construct($manager);
    }

    protected function send(MarketplaceStore $store, CatalogProduct $product, Collection $images, array $data): array
    {
        // Lazada menyalin foto dari URL publik, jadi alamat foto bizbos harus bisa dibuka dari internet.
        $lazadaImages = $images->map(fn (CatalogProductImage $i) => $this->lazada->migrateImage($store, url($i->url())))->all();
        $payload = $this->payload($product, $lazadaImages, $data);
        $result = $this->lazada->createProduct($store, $payload);

        return ['item_id' => $result['item_id'], 'payload' => $payload, 'response' => $result['raw']];
    }

    /** Payload JSON CreateProduct: Request.Product.{PrimaryCategory, Images, Attributes, Skus.Sku[]}. */
    public function payload(CatalogProduct $product, array $images, array $data): array
    {
        $lines = $this->descriptionLines($product);
        $description = collect($lines)->map(fn ($line) => '<p>'.e($line).'</p>')->implode('');
        $features = array_slice($product->features(), 0, 8);
        $short = $features ? '<ul>'.collect($features)->map(fn ($f) => '<li>'.e(mb_substr($f, 0, 200)).'</li>')->implode('').'</ul>' : null;

        $attributes = array_filter([
            ...$this->flatten(collect($data['attributes'] ?? [])->except(self::PRODUCT_FIELDS)->all()),
            'name' => mb_substr($product->name, 0, 255),
            'description' => $description ?: '<p>'.e($product->name).'</p>',
            'short_description' => $short,
            'brand_id' => (string) $data['brand_id'],
        ], fn ($v) => $v !== null && $v !== '');

        $sku = array_filter([
            ...$this->flatten(collect($data['sku_attributes'] ?? [])->except(self::SKU_FIELDS)->all()),
            'SellerSku' => (string) $data['seller_sku'],
            'quantity' => (string) (int) $data['quantity'],
            'price' => (string) (int) $data['price'],
            'package_weight' => (string) $data['package_weight'],
            'package_length' => (string) $data['package_length'],
            'package_width' => (string) $data['package_width'],
            'package_height' => (string) $data['package_height'],
            'package_content' => $data['package_content'] ?? null,
            'Images' => ['Image' => $images],
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($data['special_price']) && (int) $data['special_price'] < (int) $data['price']) {
            $sku += [
                'special_price' => (string) (int) $data['special_price'],
                'special_from_date' => now()->format('Y-m-d H:i:s'),
                'special_to_date' => now()->addYear()->format('Y-m-d H:i:s'),
            ];
        }

        return ['Request' => ['Product' => [
            'PrimaryCategory' => (string) $data['category_id'],
            'Images' => ['Image' => $images],
            'Attributes' => $attributes,
            'Skus' => ['Sku' => [$sku]],
        ]]];
    }

    /** Atribut pilihan-banyak dikirim sebagai teks dipisah koma. */
    private function flatten(array $values): array
    {
        return array_map(fn ($v) => is_array($v) ? implode(',', array_filter($v)) : $v, $values);
    }
}
