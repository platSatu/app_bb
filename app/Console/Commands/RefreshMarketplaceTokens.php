<?php

namespace App\Console\Commands;

use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Console\Command;

/** Perpanjang token toko marketplace yang habis dalam 2 hari ke depan (dijadwalkan di routes/console.php). */
class RefreshMarketplaceTokens extends Command
{
    protected $signature = 'marketplace:refresh-tokens';

    protected $description = 'Perpanjang access token toko marketplace (Lazada, dst.) sebelum kedaluwarsa';

    public function handle(MarketplaceManager $manager): int
    {
        $done = 0;
        $failed = 0;

        MarketplaceStore::with('app')
            ->where('status', MarketplaceStore::STATUS_CONNECTED)
            ->where(fn ($q) => $q->whereNull('access_expires_at')->orWhere('access_expires_at', '<', now()->addDays(2)))
            ->chunkById(50, function ($stores) use ($manager, &$done, &$failed) {
                foreach ($stores as $store) {
                    if (! $manager->enabled($store->channel) || ! $store->canRefresh()) {
                        continue;
                    }

                    try {
                        $manager->for($store)->refreshToken($store);
                        $done++;
                    } catch (MarketplaceException $e) {
                        $failed++;
                        $this->warn("{$store->channel} {$store->displayName()}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Token diperpanjang: {$done}, gagal: {$failed}.");

        return self::SUCCESS;
    }
}
