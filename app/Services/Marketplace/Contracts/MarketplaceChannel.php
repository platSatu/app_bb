<?php

namespace App\Services\Marketplace\Contracts;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;

/**
 * Kontrak satu channel marketplace (Lazada, TikTok Shop, Shopee).
 * Controller & sinkron hanya bicara lewat kontrak ini, jadi channel baru cukup menambah driver.
 */
interface MarketplaceChannel
{
    /** Kunci channel: lazada | tiktok | shopee. */
    public function key(): string;

    /** URL halaman otorisasi seller. $state dikembalikan apa adanya ke callback. */
    public function authorizeUrl(MarketplaceApp $app, string $state): string;

    /**
     * Tukar kode otorisasi menjadi token toko.
     *
     * @return array{external_id: string, name: ?string, country: ?string, account: ?string, short_code: ?string,
     *               access_token: string, refresh_token: ?string, access_expires_at: ?\DateTimeInterface,
     *               refresh_expires_at: ?\DateTimeInterface, meta: array}
     */
    public function exchangeCode(MarketplaceApp $app, string $code): array;

    /** Perpanjang token akses toko, simpan hasilnya ke $store. */
    public function refreshToken(MarketplaceStore $store): void;

    /**
     * Satu halaman produk toko, sudah dalam bentuk seragam.
     *
     * @return array{total: int, items: array<int, array{external_id: string, name: string, sku: ?string, price: int,
     *               special_price: ?int, stock: int, status: ?string, category_id: ?string, image: ?string,
     *               url: ?string, external_updated_at: ?\DateTimeInterface, raw: array}>}
     */
    public function fetchProducts(MarketplaceStore $store, int $offset, int $limit): array;
}
