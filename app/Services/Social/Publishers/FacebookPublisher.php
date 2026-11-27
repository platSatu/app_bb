<?php

namespace App\Services\Social\Publishers;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialPublisher;
use App\Services\Social\MetaGraph;
use App\Services\Social\SocialException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Facebook Page (Page access token).
 *  - gambar:   POST /{page}/photos (langsung terbit).
 *  - carousel: tiap foto diunggah tanpa terbit, lalu satu posting /{page}/feed berisi semua foto.
 *  - reel:     Reels Publishing API: start -> unggah dari link (rupload, header file_url) -> finish -> dicek sampai selesai.
 */
class FacebookPublisher implements SocialPublisher
{
    public function __construct(private readonly MetaGraph $graph) {}

    public function publish(ContentPublication $publication, ContentPost $post, SocialAccount $account, array $urls): array
    {
        $page = $account->external_id;
        $token = (string) $account->access_token;
        $caption = $post->fullCaption(5000);

        if ($post->format === 'reel') {
            return $this->reel($page, $token, $urls[0], $caption);
        }

        if (count($urls) === 1) {
            $result = $this->graph->post($page.'/photos', ['url' => $urls[0], 'message' => $caption, 'published' => 'true'], $token);
            $id = (string) ($result['post_id'] ?? $result['id'] ?? '');

            return ['status' => 'published', 'external_id' => $id ?: null, 'permalink' => $id ? 'https://www.facebook.com/'.$id : null];
        }

        $form = ['message' => $caption];

        foreach (array_values(array_slice($urls, 0, 10)) as $i => $url) {
            $photo = $this->graph->post($page.'/photos', ['url' => $url, 'published' => 'false'], $token)['id'] ?? null;

            if (! $photo) {
                throw new SocialException('Facebook tidak menerima salah satu foto.');
            }

            $form['attached_media['.$i.']'] = json_encode(['media_fbid' => (string) $photo]);
        }

        $id = (string) ($this->graph->post($page.'/feed', $form, $token)['id'] ?? '');

        return ['status' => 'published', 'external_id' => $id ?: null, 'permalink' => $id ? 'https://www.facebook.com/'.$id : null];
    }

    public function check(ContentPublication $publication, ContentPost $post, SocialAccount $account): array
    {
        $video = $this->graph->get((string) $publication->remote_id, ['fields' => 'status,permalink_url'], (string) $account->access_token);
        $status = (array) ($video['status'] ?? []);
        $phase = (string) ($status['publishing_phase']['status'] ?? '');

        if (($status['video_status'] ?? null) === 'error' || $phase === 'error') {
            $reason = $status['processing_phase']['errors'][0]['message'] ?? $status['publishing_phase']['errors'][0]['message'] ?? 'video ditolak.';

            throw new SocialException('Facebook: '.strip_tags((string) $reason));
        }

        if ($phase !== 'complete') {
            return ['status' => 'processing'];
        }

        $link = (string) ($video['permalink_url'] ?? '');

        return [
            'status' => 'published',
            'external_id' => (string) $publication->remote_id,
            'permalink' => $link === '' ? null : (str_starts_with($link, 'http') ? $link : 'https://www.facebook.com'.$link),
        ];
    }

    private function reel(string $page, string $token, string $url, string $caption): array
    {
        $start = $this->graph->post($page.'/video_reels', ['upload_phase' => 'start'], $token);
        $videoId = (string) ($start['video_id'] ?? '');
        $uploadUrl = (string) ($start['upload_url'] ?? '');

        // Hanya ke host unggah Meta (bukan alamat lain dari balasan).
        if ($videoId === '' || ! str_starts_with($uploadUrl, 'https://rupload.facebook.com/')) {
            throw new SocialException('Facebook tidak memberi alamat unggah Reels.');
        }

        try {
            $upload = Http::timeout(120)->connectTimeout(15)->acceptJson()
                ->withHeaders(['Authorization' => 'OAuth '.$token, 'file_url' => $url])->post($uploadUrl);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Unggah Reels ke Facebook terputus. Coba lagi.');
        }

        if (! (MetaGraph::json($upload)['success'] ?? false)) {
            throw new SocialException('Facebook tidak bisa mengambil file video dari link media.');
        }

        $finish = $this->graph->post($page.'/video_reels', [
            'upload_phase' => 'finish', 'video_id' => $videoId, 'video_state' => 'PUBLISHED', 'description' => $caption,
        ], $token);

        if (! ($finish['success'] ?? false)) {
            throw new SocialException('Facebook menolak menerbitkan Reels.');
        }

        return ['status' => 'processing', 'remote_id' => $videoId];
    }
}
