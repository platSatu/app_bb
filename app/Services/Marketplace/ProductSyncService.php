<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceProduct;
use App\Models\MarketplaceStore;

/** Tarik produk toko marketplace ke tabel marketplace_products (berlaku untuk semua channel). */
class ProductSyncService
{
    private const PAGE_SIZE = 50;

    public function __construct(private readonly MarketplaceManager $manager) {}

    /** @return array{fetched: int, total: int} */
    public function sync(MarketplaceStore $store): array
    {
        $this->manager->ensureFreshToken($store);
        $channel = $this->manager->for($store);
        $max = max(self::PAGE_SIZE, (int) config('marketplace.sync_max_products', 1000));

        $offset = 0;
        $total = 0;

        do {
            try {
                $page = $channel->fetchProducts($store, $offset, self::PAGE_SIZE);
            } catch (MarketplaceException $e) {
                if ($e->isAuthError()) {
                    $store->forceFill(['status' => MarketplaceStore::STATUS_EXPIRED, 'status_reason' => $e->getMessage()])->save();
                }

                throw $e;
            }

            $total = $page['total'];

            foreach ($page['items'] as $item) {
                $this->upsert($store, $item);
            }

            $offset += count($page['items']);
        } while (count($page['items']) === self::PAGE_SIZE && $offset < $max && $offset < $total);

        $store->forceFill(['last_synced_at' => now(), 'status' => MarketplaceStore::STATUS_CONNECTED, 'status_reason' => null])->save();

        return ['fetched' => $offset, 'total' => $total];
    }

    private function upsert(MarketplaceStore $store, array $item): void
    {
        $key = ['store_id' => $store->getKey(), 'external_id' => (string) $item['external_id']];
        $product = MarketplaceProduct::where($key)->first() ?? (new MarketplaceProduct())->forceFill($key);

        $product->forceFill([
            'page_id' => $store->page_id,
            'name' => mb_substr((string) $item['name'], 0, 255),
            'sku' => $item['sku'] !== null ? mb_substr((string) $item['sku'], 0, 150) : null,
            'price' => max(0, (int) $item['price']),
            'special_price' => $item['special_price'] !== null ? max(0, (int) $item['special_price']) : null,
            'stock' => max(0, (int) $item['stock']),
            'status' => $item['status'] !== null ? mb_substr((string) $item['status'], 0, 50) : null,
            'category_id' => $item['category_id'],
            'image' => $item['image'],
            'url' => $item['url'],
            'raw' => $item['raw'],
            'external_updated_at' => $item['external_updated_at'],
        ])->save();
    }
}
