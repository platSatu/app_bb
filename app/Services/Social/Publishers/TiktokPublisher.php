<?php

namespace App\Services\Social\Publishers;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Services\Ai\ClassaiMedia;
use App\Services\Social\Connectors\TiktokConnector;
use App\Services\Social\Contracts\SocialPublisher;
use App\Services\Social\SocialException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * TikTok Content Posting API (Direct Post).
 *  - video: /post/publish/video/init/ (FILE_UPLOAD) lalu file dikirim per potongan (PUT, Content-Range).
 *  - foto:  /post/publish/content/init/ (PULL_FROM_URL; domain link media wajib diverifikasi di TikTok for Developers).
 *  - check: /post/publish/status/fetch/ sampai PUBLISH_COMPLETE / FAILED.
 * Privasi wajib dipilih superadmin saat menjadwalkan (sesuai pilihan creator_info akun); tidak ada nilai bawaan.
 */
class TiktokPublisher implements SocialPublisher
{
    private const SINGLE_MAX = 64 * 1024 * 1024;

    private const CHUNK = 10 * 1024 * 1024;

    public function publish(ContentPublication $publication, ContentPost $post, SocialAccount $account, array $urls): array
    {
        $options = (array) (($post->options ?? [])['tiktok'] ?? []);
        $privacy = (string) ($options['privacy'] ?? '');

        if (! in_array($privacy, $account->tiktokPrivacyOptions(), true)) {
            throw new SocialException('Pilihan privasi TikTok belum dipilih atau tidak tersedia untuk akun ini. Jadwalkan ulang dan pilih privasinya.');
        }

        $token = (string) $account->access_token;
        $interact = [
            'disable_comment' => ! ($options['comment'] ?? false) || ! empty($account->meta['comment_disabled']),
            'brand_content_toggle' => false,
            'brand_organic_toggle' => true, // mempromosikan usaha sendiri (bizbos)
        ];

        if ($post->format !== 'reel') {
            $data = TiktokConnector::api('post', '/post/publish/content/init/', $token, [
                'post_info' => [
                    'title' => mb_substr($post->title, 0, 90),
                    'description' => $post->fullCaption(4000),
                    'privacy_level' => $privacy,
                    'auto_add_music' => true,
                ] + $interact,
                'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => array_slice($urls, 0, 35)],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
                'is_aigc' => (bool) $post->media_ai,
            ]);

            return ['status' => 'processing', 'remote_id' => $this->publishId($data)];
        }

        $file = $post->mediaFiles()[0];
        $disk = Storage::disk(ClassaiMedia::DISK);
        $size = (int) $disk->size($file['path']);
        [$chunk, $count] = $size <= self::SINGLE_MAX ? [$size, 1] : [self::CHUNK, intdiv($size, self::CHUNK)];

        $data = TiktokConnector::api('post', '/post/publish/video/init/', $token, [
            'post_info' => [
                'title' => $post->fullCaption(2200),
                'privacy_level' => $privacy,
                'disable_duet' => ! ($options['duet'] ?? false) || ! empty($account->meta['duet_disabled']),
                'disable_stitch' => ! ($options['stitch'] ?? false) || ! empty($account->meta['stitch_disabled']),
                'is_aigc' => (bool) $post->media_ai,
            ] + $interact,
            'source_info' => ['source' => 'FILE_UPLOAD', 'video_size' => $size, 'chunk_size' => $chunk, 'total_chunk_count' => $count],
        ]);

        $publishId = $this->publishId($data);
        $uploadUrl = (string) ($data['upload_url'] ?? '');

        if (! str_starts_with($uploadUrl, 'https://')) {
            throw new SocialException('TikTok tidak memberi alamat unggah video.');
        }

        $this->upload($uploadUrl, $file['path'], (string) $file['mime'], $size, $chunk, $count);

        return ['status' => 'processing', 'remote_id' => $publishId];
    }

    public function check(ContentPublication $publication, ContentPost $post, SocialAccount $account): array
    {
        $data = TiktokConnector::api('post', '/post/publish/status/fetch/', (string) $account->access_token, ['publish_id' => (string) $publication->remote_id]);
        $status = (string) ($data['status'] ?? '');

        if ($status === 'FAILED') {
            throw new SocialException('TikTok gagal memproses posting: '.strip_tags((string) ($data['fail_reason'] ?? 'tanpa keterangan')));
        }

        if ($status !== 'PUBLISH_COMPLETE') {
            return ['status' => 'processing'];
        }

        // Nama field memang "publicaly_..." di API TikTok; ejaan benar dicek juga. Kosong untuk posting privat.
        $ids = (array) ($data['publicaly_available_post_id'] ?? $data['publicly_available_post_id'] ?? []);
        $id = isset($ids[0]) ? (string) $ids[0] : null;
        $user = ltrim((string) $account->username, '@');

        return [
            'status' => 'published',
            'external_id' => $id,
            'permalink' => $id && $user !== '' ? 'https://www.tiktok.com/@'.rawurlencode($user).'/'.($post->format === 'reel' ? 'video' : 'photo').'/'.$id : null,
        ];
    }

    private function publishId(array $data): string
    {
        $id = (string) ($data['publish_id'] ?? '');

        if ($id === '' || strlen($id) > 150) {
            throw new SocialException('TikTok tidak mengembalikan publish_id.');
        }

        return $id;
    }

    /** Kirim file berurutan; potongan terakhir menampung sisa byte (aturan TikTok). */
    private function upload(string $url, string $path, string $mime, int $size, int $chunk, int $count): void
    {
        $stream = Storage::disk(ClassaiMedia::DISK)->readStream($path);

        if (! is_resource($stream)) {
            throw new SocialException('File video tidak ditemukan di server.');
        }

        try {
            for ($i = 0; $i < $count; $i++) {
                $start = $i * $chunk;
                $end = $i === $count - 1 ? $size - 1 : $start + $chunk - 1;
                $bytes = stream_get_contents($stream, $end - $start + 1, $start);

                $response = Http::timeout(300)->connectTimeout(15)
                    ->withHeaders(['Content-Range' => "bytes {$start}-{$end}/{$size}"])
                    ->withBody((string) $bytes, $mime ?: 'video/mp4')->put($url);

                if (! in_array($response->status(), [201, 206], true)) {
                    throw new SocialException('Unggah video ke TikTok gagal (HTTP '.$response->status().').');
                }
            }
        } catch (SocialException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Unggah video ke TikTok terputus. Coba lagi.');
        } finally {
            fclose($stream);
        }
    }
}
