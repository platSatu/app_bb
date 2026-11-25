<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\MarketplaceChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Daftar channel dari config/marketplace.php dan pembuat driver-nya. */
class MarketplaceManager
{
    private array $drivers = [];

    /** Semua channel (aktif maupun "segera hadir") untuk ditampilkan. */
    public function channels(): Collection
    {
        return collect(config('marketplace.channels', []));
    }

    public function enabled(string $key): bool
    {
        return (bool) config("marketplace.channels.{$key}.enabled") && config("marketplace.channels.{$key}.driver");
    }

    public function channel(string $key): MarketplaceChannel
    {
        if (! $this->enabled($key)) {
            throw new MarketplaceException('Channel '.$key.' belum tersedia.');
        }

        return $this->drivers[$key] ??= app(config("marketplace.channels.{$key}.driver"));
    }

    public function for(MarketplaceStore $store): MarketplaceChannel
    {
        return $this->channel($store->channel);
    }

    /** Pastikan token toko masih berlaku sebelum memanggil API; perpanjang otomatis bila hampir habis. */
    public function ensureFreshToken(MarketplaceStore $store): void
    {
        $channel = $this->for($store);

        if ($store->needsRefresh($channel->refreshMarginMinutes()) && $store->canRefresh()) {
            $this->refresh($store);
        }

        if ($store->accessExpired()) {
            $store->forceFill(['status' => MarketplaceStore::STATUS_EXPIRED, 'status_reason' => 'Token akses habis.'])->save();

            throw new MarketplaceException('Token toko '.$store->displayName().' sudah habis. Sambungkan ulang tokonya.');
        }
    }

    /**
     * Perpanjang token dengan kunci per toko. Refresh token Shopee hanya bisa dipakai sekali,
     * jadi dua proses yang memperpanjang bersamaan akan membuat salah satunya gagal.
     */
    public function refresh(MarketplaceStore $store): void
    {
        Cache::lock('marketplace:refresh:'.$store->getKey(), 30)->block(20, function () use ($store) {
            $store->refresh();

            // Proses lain mungkin baru saja memperpanjang token ini.
            if (! $store->needsRefresh($this->for($store)->refreshMarginMinutes())) {
                return;
            }

            $this->for($store)->refreshToken($store);
        });
    }
}
