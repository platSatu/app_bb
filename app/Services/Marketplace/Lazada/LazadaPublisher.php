<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;

/**
 * Unggah produk ke toko Lazada (CreateProduct). Sumber produk: Catalog Online sendiri,
 * atau produk yang ditarik dari marketplace lain. Setiap percobaan dicatat di marketplace_listings.
 */
class LazadaPublisher
{
    // Atribut SKU yang sudah diisi dari kolom form tersendiri.
    public const SKU_FIELDS = ['SellerSku', 'quantity', 'price', 'special_price', 'special_from_date', 'special_to_date',
        'package_weight', 'package_length', 'package_width', 'package_height', 'package_content', '__images__', 'color_thumbnail'];

    // Atribut produk yang sudah diisi dari kolom form tersendiri.
    public const PRODUCT_FIELDS = ['name', 'description', 'short_description', 'brand', 'brand_id'];

    public function __construct(
        private readonly LazadaChannel $lazada,
        private readonly MarketplaceManager $manager,
    ) {}

    /**
     * @param  array{category_id: string, brand_id: string, seller_sku: string, quantity: int, price: int, special_price: ?int,
     *               package_weight: float, package_length: float, package_width: float, package_height: float,
     *               package_content: ?string, attributes: array, sku_attributes: array}  $data
     */
    public function publishCatalogProduct(MarketplaceStore $store, CatalogProduct $product, array $data): MarketplaceListing
    {
        $images = $product->images()->active()->limit(8)->get()->map(fn (CatalogProductImage $i) => url($i->url()))->all();

        return $this->publish($store, MarketplaceListing::SOURCE_CATALOG, $product->getKey(), [
            'name' => $product->name,
            'description' => $product->description,
            'features' => $product->features(),
            'images' => $images,
        ], $data);
    }

    /** Jual ulang produk yang ditarik dari toko marketplace lain (mis. produk Lazada toko A ke Lazada toko B). */
    public function publishMarketplaceProduct(MarketplaceStore $store, MarketplaceProduct $product, array $data): MarketplaceListing
    {
        $images = collect([$product->image, ...(array) data_get($product->raw, 'skus.0.Images', [])])->filter()->unique()->take(8)->values()->all();

        return $this->publish($store, MarketplaceListing::SOURCE_MARKETPLACE, $product->getKey(), [
            'name' => $product->name,
            'description' => strip_tags((string) data_get($product->raw, 'attributes.description', '')),
            'features' => [],
            'images' => $images,
        ], $data);
    }

    private function publish(MarketplaceStore $store, string $sourceType, string $sourceId, array $source, array $data): MarketplaceListing
    {
        $listing = new MarketplaceListing();
        $listing->forceFill([
            'page_id' => $store->page_id,
            'store_id' => $store->getKey(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'external_sku' => $data['seller_sku'],
        ]);

        try {
            $this->manager->ensureFreshToken($store);

            if ($source['images'] === []) {
                throw new MarketplaceException('Produk wajib punya minimal 1 foto aktif untuk diunggah ke Lazada.');
            }

            $lazadaImages = array_map(fn (string $url) => $this->lazada->migrateImage($store, $url), $source['images']);
            $payload = $this->payload($source, $lazadaImages, $data);
            $listing->payload = $payload;

            $result = $this->lazada->createProduct($store, $payload);

            $listing->forceFill([
                'status' => MarketplaceListing::STATUS_SUBMITTED,
                'external_id' => $result['item_id'] ?: null,
                'response' => $result['raw'],
                'error' => null,
                'published_at' => now(),
            ]);
        } catch (MarketplaceException $e) {
            $listing->forceFill(['status' => MarketplaceListing::STATUS_FAILED, 'error' => $e->getMessage(), 'response' => $e->context() ?: null]);
        }

        $listing->save();

        return $listing;
    }

    /** Susun payload JSON CreateProduct sesuai dokumentasi Lazada. */
    public function payload(array $source, array $images, array $data): array
    {
        $description = collect(preg_split('/\R/', (string) $source['description']))
            ->map(fn ($line) => trim($line))->filter()
            ->map(fn ($line) => '<p>'.e($line).'</p>')->implode('');

        $features = array_slice($source['features'], 0, 8);
        $short = $features ? '<ul>'.collect($features)->map(fn ($f) => '<li>'.e(mb_substr($f, 0, 200)).'</li>')->implode('').'</ul>' : null;

        $attributes = array_filter([
            ...collect($data['attributes'] ?? [])->except(self::PRODUCT_FIELDS)->map(fn ($v) => is_array($v) ? implode(',', $v) : $v)->all(),
            'name' => mb_substr((string) $source['name'], 0, 255),
            'description' => $description ?: '<p>'.e($source['name']).'</p>',
            'short_description' => $short,
            'brand_id' => (string) $data['brand_id'],
        ], fn ($v) => $v !== null && $v !== '');

        $sku = array_filter([
            ...collect($data['sku_attributes'] ?? [])->except(self::SKU_FIELDS)->map(fn ($v) => is_array($v) ? implode(',', $v) : $v)->all(),
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
}
