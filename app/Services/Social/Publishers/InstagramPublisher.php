<?php

namespace App\Services\Social\Publishers;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialPublisher;
use App\Services\Social\MetaGraph;
use App\Services\Social\SocialException;
use Throwable;

/**
 * Instagram Content Publishing API (Instagram professional yang terhubung ke Facebook Page).
 *  1. publish(): buat media container (gambar / carousel / REELS) dari link media -> "processing".
 *  2. check():   status_code FINISHED -> media_publish -> "published". Instagram mengambil file dari link kita (cURL),
 *                jadi link harus bisa dibuka publik saat itu (link bertanda tangan, berlaku beberapa jam).
 */
class InstagramPublisher implements SocialPublisher
{
    public function __construct(private readonly MetaGraph $graph) {}

    public function publish(ContentPublication $publication, ContentPost $post, SocialAccount $account, array $urls): array
    {
        $ig = $account->external_id;
        $token = (string) $account->access_token;
        $label = $post->media_ai ? ['is_ai_generated' => 'true'] : [];
        $caption = $post->fullCaption(2200);

        if ($post->format === 'reel') {
            $container = $this->graph->post($ig.'/media', [
                'media_type' => 'REELS', 'video_url' => $urls[0], 'caption' => $caption, 'share_to_feed' => 'true',
            ] + $label, $token)['id'] ?? null;
        } elseif (count($urls) >= 2) {
            $children = [];

            foreach (array_slice($urls, 0, 10) as $url) {
                $children[] = $this->graph->post($ig.'/media', ['image_url' => $url, 'is_carousel_item' => 'true'], $token)['id'] ?? null;
            }

            if (in_array(null, $children, true)) {
                throw new SocialException('Instagram tidak membuat salah satu slide carousel.');
            }

            $container = $this->graph->post($ig.'/media', [
                'media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption,
            ] + $label, $token)['id'] ?? null;
        } else {
            $container = $this->graph->post($ig.'/media', ['image_url' => $urls[0], 'caption' => $caption] + $label, $token)['id'] ?? null;
        }

        if (! is_string($container) && ! is_int($container)) {
            throw new SocialException('Instagram tidak mengembalikan ID container.');
        }

        return ['status' => 'processing', 'remote_id' => (string) $container];
    }

    public function check(ContentPublication $publication, ContentPost $post, SocialAccount $account): array
    {
        $token = (string) $account->access_token;
        $state = $this->graph->get((string) $publication->remote_id, ['fields' => 'status_code,status'], $token);
        $code = (string) ($state['status_code'] ?? '');

        if ($code === 'IN_PROGRESS' || $code === '') {
            return ['status' => 'processing'];
        }

        if ($code === 'PUBLISHED') {
            // Sudah terbit (balasan media_publish sebelumnya tidak sempat tercatat): jangan diterbitkan lagi.
            return ['status' => 'published', 'external_id' => null, 'permalink' => null];
        }

        if ($code !== 'FINISHED') {
            throw new SocialException('Instagram menolak media ('.$code.'): '.strip_tags((string) ($state['status'] ?? 'periksa format & ukuran file.')));
        }

        $id = $this->graph->post($account->external_id.'/media_publish', ['creation_id' => $publication->remote_id], $token)['id'] ?? null;

        if (! $id) {
            throw new SocialException('Instagram tidak mengembalikan ID posting. Cek akun Instagram sebelum mencoba lagi.');
        }

        try {
            $permalink = $this->graph->get((string) $id, ['fields' => 'permalink'], $token)['permalink'] ?? null;
        } catch (Throwable) {
            $permalink = null;
        }

        return ['status' => 'published', 'external_id' => (string) $id, 'permalink' => is_string($permalink) ? $permalink : null];
    }
}
