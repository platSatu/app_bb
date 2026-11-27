<?php

namespace App\Services\Social;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Services\Social\Connectors\GoogleConnector;
use App\Services\Social\Connectors\MetaConnector;
use App\Services\Social\Connectors\TiktokConnector;
use App\Services\Social\Contracts\SocialConnector;
use App\Services\Social\Contracts\SocialPublisher;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\InstagramPublisher;
use App\Services\Social\Publishers\TiktokPublisher;
use App\Services\Social\Publishers\YoutubePublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Satu pintu Tools › Akun Sosmed & pengiriman konten terjadwal.
 *
 * Pengaman kiriman (supaya tidak pernah posting dobel):
 *  - pending -> publishing dan processing -> publishing diklaim atomik; hanya satu proses yang bisa mengirim.
 *  - Kiriman yang gagal tidak diulang otomatis; superadmin menekan "Coba lagi" setelah mengecek akunnya.
 *  - Kiriman yang terhenti di tengah jalan (worker mati) ditandai gagal oleh content:publish, bukan diulang.
 */
class SocialManager
{
    /** Link media untuk platform berlaku selama ini (Instagram/TikTok mengambil file beberapa saat setelah diminta). */
    public const MEDIA_LINK_HOURS = 6;

    public function connector(string $provider): SocialConnector
    {
        return match ($provider) {
            'meta' => new MetaConnector(),
            'tiktok' => new TiktokConnector(),
            'google' => new GoogleConnector(),
            default => throw new SocialException('Penyedia tidak dikenal.'),
        };
    }

    public function publisher(string $platform): SocialPublisher
    {
        $graph = fn () => new MetaGraph(SocialApp::for('meta')?->graphVersion() ?? SocialApp::DEFAULT_GRAPH_VERSION);

        return match ($platform) {
            'instagram' => new InstagramPublisher($graph()),
            'facebook' => new FacebookPublisher($graph()),
            'tiktok' => new TiktokPublisher(),
            'youtube' => new YoutubePublisher(),
            default => throw new SocialException('Platform tidak dikenal.'),
        };
    }

    /** Perpanjang token kalau hampir habis (TikTok, YouTube). Token ditolak = akun ditandai perlu disambungkan ulang. */
    public function ensureToken(SocialAccount $account, int $minutes = 10): void
    {
        if (! $account->needsRefresh($minutes)) {
            return;
        }

        $this->refresh($account);
    }

    public function refresh(SocialAccount $account): void
    {
        $provider = SocialApp::providerOf($account->platform);
        $app = $provider ? SocialApp::for($provider) : null;

        if (! $app) {
            throw new SocialException('Kredensial app '.$account->platformLabel().' belum diisi di Tools › Akun Sosmed.');
        }

        try {
            $this->connector($provider)->refresh($account, $app);
        } catch (SocialException $e) {
            if ($e->expired) {
                $account->markExpired($e->getMessage());
            }

            throw $e;
        }
    }

    /** @return list<string> link bertanda tangan untuk tiap file media, urut. */
    public function mediaUrls(ContentPost $post): array
    {
        return collect($post->mediaFiles())->values()->map(fn ($file, $i) => URL::temporarySignedRoute(
            'content.media', now()->addHours(self::MEDIA_LINK_HOURS),
            ['post' => $post->getKey(), 'file' => $i.'.'.pathinfo($file['path'], PATHINFO_EXTENSION)],
        ))->all();
    }

    /** Kirim satu konten yang jadwalnya sudah tiba (dipanggil PublishContent). */
    public function start(ContentPublication $publication): void
    {
        if (! $publication->claim('pending', 'publishing')) {
            return;
        }

        $publication->forceFill(['attempts' => $publication->attempts + 1, 'started_at' => now(), 'error' => null])->save();
        $post = $publication->post;
        $account = $publication->account;

        try {
            if (! $post || $post->status !== 'scheduled' || ! $post->scheduled_at || $post->scheduled_at->isFuture()) {
                throw new SocialException('Jadwal konten berubah sebelum dikirim.');
            }

            if (! $account || ! $account->isConnected()) {
                throw new SocialException('Akun tujuan terputus. Sambungkan ulang di Tools › Akun Sosmed, lalu coba lagi.');
            }

            if (! in_array($account->platform, $post->allowedPlatforms(), true) || ! $post->hasMedia()) {
                throw new SocialException('Konten ini belum punya media yang cocok untuk '.$account->platformLabel().'.');
            }

            $this->ensureToken($account);
            $this->apply($publication, $account, $this->publisher($account->platform)->publish($publication, $post, $account, $this->mediaUrls($post)));
        } catch (Throwable $e) {
            $this->failed($publication, $account, $e);
        }

        $post?->refreshPublishStatus();
    }

    /** Cek kiriman yang masih diproses platform (dipanggil PublishContent tiap menit). */
    public function check(ContentPublication $publication): void
    {
        if (! $publication->claim('processing', 'publishing')) {
            return;
        }

        $post = $publication->post;
        $account = $publication->account;

        try {
            if (! $post || ! $account) {
                throw new SocialException('Konten atau akun tujuan sudah dihapus.');
            }

            $this->ensureToken($account);
            $this->apply($publication, $account, $this->publisher($account->platform)->check($publication, $post, $account));
        } catch (Throwable $e) {
            $this->failed($publication, $account, $e);
        }

        $post?->refreshPublishStatus();
    }

    private function apply(ContentPublication $publication, SocialAccount $account, array $result): void
    {
        if (($result['status'] ?? null) === 'published') {
            $publication->forceFill([
                'status' => 'published',
                'external_id' => isset($result['external_id']) ? mb_substr((string) $result['external_id'], 0, 150) : $publication->external_id,
                'permalink' => isset($result['permalink']) && str_starts_with((string) $result['permalink'], 'https://') ? $result['permalink'] : $publication->permalink,
                'published_at' => now(),
                'error' => null,
            ])->save();
            $account->forceFill(['last_used_at' => now()])->save();

            return;
        }

        $publication->forceFill([
            'status' => 'processing',
            'remote_id' => isset($result['remote_id']) ? mb_substr((string) $result['remote_id'], 0, 150) : $publication->remote_id,
        ])->save();
    }

    private function failed(ContentPublication $publication, ?SocialAccount $account, Throwable $e): void
    {
        if ($e instanceof SocialException) {
            $message = $e->getMessage();

            if ($e->expired && $account) {
                $account->markExpired($message);
            }
        } else {
            report($e);
            $message = 'Terjadi kesalahan tak terduga saat mengirim. Cek akun '.($account?->platformLabel() ?? '').' sebelum mencoba lagi.';
        }

        Log::warning('[CONTENT-PUBLISH] Gagal', ['publication' => $publication->getKey(), 'platform' => $publication->platform, 'message' => $message]);
        $publication->forceFill(['status' => 'failed', 'error' => mb_substr($message, 0, 500)])->save();
    }
}
