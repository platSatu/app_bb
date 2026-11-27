<?php

namespace App\Jobs;

use App\Models\ContentPublication;
use App\Services\Social\SocialManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim (mode "start") atau cek status (mode "check") satu konten ke satu akun sosmed.
 * Tidak diulang otomatis ($tries = 1): klaim status di SocialManager mencegah kiriman dobel.
 */
class PublishContent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Unggah video YouTube/TikTok bisa beberapa menit. */
    public int $timeout = 900;

    public function __construct(public string $publicationId, public string $mode = 'start')
    {
        $this->afterCommit();
    }

    public function handle(SocialManager $social): void
    {
        $publication = ContentPublication::with(['post', 'account'])->find($this->publicationId);

        if (! $publication) {
            return;
        }

        $this->mode === 'check' ? $social->check($publication) : $social->start($publication);
    }
}
