<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\SocialException;
use App\Services\Social\SocialManager;
use Illuminate\Console\Command;

/**
 * Tools › Akun Sosmed, tiap jam: perpanjang token TikTok/YouTube yang tinggal < 3 jam, dan sekali sehari cek
 * token Instagram/Facebook masih berlaku. Token ditolak = akun ditandai "perlu disambungkan ulang".
 */
class RefreshSocialTokens extends Command
{
    protected $signature = 'social:refresh-tokens';

    protected $description = 'Perpanjang / cek token akun sosmed (TikTok, YouTube, Instagram, Facebook)';

    public function handle(SocialManager $social): int
    {
        $done = 0;
        $failed = 0;

        SocialAccount::connected()->chunkById(50, function ($accounts) use ($social, &$done, &$failed) {
            foreach ($accounts as $account) {
                $meta = in_array($account->platform, ['instagram', 'facebook'], true);
                $checkedAt = $account->meta['checked_at'] ?? null;

                if ($meta ? ($checkedAt && now()->subDay()->lt($checkedAt)) : ! $account->needsRefresh(180)) {
                    continue;
                }

                try {
                    $social->refresh($account);
                    $account->forceFill(['meta' => array_merge($account->meta ?? [], ['checked_at' => now()->toIso8601String()])])->save();
                    $done++;
                } catch (SocialException $e) {
                    $failed++;
                    $this->warn("{$account->platform} {$account->displayName()}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Diperbarui: {$done}, gagal: {$failed}.");

        return self::SUCCESS;
    }
}
