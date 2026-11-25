<?php

namespace App\Services\Marketplace;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceStore;
use Illuminate\Support\Collection;

/**
 * Induk publisher per channel. Mengurus bagian yang sama di semua marketplace:
 * token, mencatat setiap percobaan ke marketplace_listings, dan menangani gagal/berhasil.
 * Publisher channel cukup mengisi send(): unggah gambar + buat produk, kembalikan item ID.
 */
abstract class ListingPublisher
{
    public function __construct(protected readonly MarketplaceManager $manager) {}

    /**
     * Unggah gambar & buat produk di marketplace.
     *
     * @return array{item_id: string, payload: array, response: array}
     */
    abstract protected function send(MarketplaceStore $store, CatalogProduct $product, Collection $images, array $data): array;

    /** Unggah produk Catalog Online ke toko. $data = isian form (sudah divalidasi). */
    public function publishCatalogProduct(MarketplaceStore $store, CatalogProduct $product, array $data): MarketplaceListing
    {
        $listing = new MarketplaceListing();
        $listing->forceFill([
            'page_id' => $store->page_id,
            'store_id' => $store->getKey(),
            'source_type' => MarketplaceListing::SOURCE_CATALOG,
            'source_id' => $product->getKey(),
            'external_sku' => $data['seller_sku'] ?? null,
        ]);

        try {
            $this->manager->ensureFreshToken($store);

            $images = $product->images()->active()->limit(8)->get();
            if ($images->isEmpty()) {
                throw new MarketplaceException('Produk wajib punya minimal 1 foto aktif untuk diunggah ke '.$store->channelLabel().'.');
            }

            $result = $this->send($store, $product, $images, $data);

            $listing->forceFill([
                'status' => MarketplaceListing::STATUS_SUBMITTED,
                'external_id' => $result['item_id'] ?: null,
                'payload' => $result['payload'],
                'response' => $result['response'],
                'error' => null,
                'published_at' => now(),
            ]);
        } catch (MarketplaceException $e) {
            $listing->forceFill(['status' => MarketplaceListing::STATUS_FAILED, 'error' => $e->getMessage(), 'response' => $e->context() ?: null]);
        }

        $listing->save();

        return $listing;
    }

    /** Isi file foto katalog dari disk public (tidak butuh URL publik). */
    protected function imageContents(CatalogProductImage $image): string
    {
        $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($image->image);

        if ($contents === null || $contents === '') {
            throw new MarketplaceException('File foto produk tidak ditemukan di server: '.$image->image);
        }

        return $contents;
    }

    /** Deskripsi katalog: baris-baris teks, tanpa baris kosong. */
    protected function descriptionLines(CatalogProduct $product): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $product->description))));
    }
}
