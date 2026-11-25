<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceStore;

/**
 * Data pendukung form upload produk yang sama bentuknya di semua channel:
 * kategori paling akhir (leaf), saran kategori & atribut kategori.
 */
interface PublishesProducts
{
    /** @return array<int, array{id: string, name: string, path: string}> */
    public function leafCategories(MarketplaceStore $store): array;

    /** @return array<int, array{id: string, name: string, path: string}> */
    public function categorySuggestions(MarketplaceStore $store, string $productName, ?string $imageUrl = null): array;

    /** Atribut kategori; bentuk tiap item ditentukan channel dan dibaca script form channel itu. */
    public function categoryAttributes(MarketplaceStore $store, string $categoryId): array;
}
