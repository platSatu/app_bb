<?php

namespace App\Services\Social\Publishers;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Services\Ai\ClassaiMedia;
use App\Services\Social\Contracts\SocialPublisher;
use App\Services\Social\SocialException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * YouTube Data API v3, videos.insert (unggah resumable). Video vertikal pendek tampil sebagai Shorts.
 * Catatan: project Google yang belum lolos audit YouTube API membuat video terkunci "Private".
 */
class YoutubePublisher implements SocialPublisher
{
    private const UPLOAD = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public function publish(ContentPublication $publication, ContentPost $post, SocialAccount $account, array $urls): array
    {
        if ($post->format !== 'reel') {
            throw new SocialException('YouTube hanya menerima video (Reels/Shorts).');
        }

        $file = $post->mediaFiles()[0];
        $disk = Storage::disk(ClassaiMedia::DISK);
        $size = (int) $disk->size($file['path']);
        $mime = (string) ($file['mime'] ?: 'video/mp4');
        $clean = fn (string $text, int $max) => mb_substr(trim(str_replace(['<', '>'], '', strip_tags($text))), 0, $max);
        $tags = collect(explode(' ', (string) $post->hashtags))->map(fn ($t) => ltrim($t, '#'))->filter()->take(10)->values()->all();

        try {
            $init = Http::timeout(60)->connectTimeout(15)->acceptJson()->withToken((string) $account->access_token)
                ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $size])
                ->post(self::UPLOAD.'?uploadType=resumable&part=snippet,status', [
                    'snippet' => [
                        'title' => $clean(Str::limit($post->title, 88, '').' #Shorts', 100),
                        'description' => $clean($post->fullCaption(4900), 5000),
                        'tags' => $tags,
                        'categoryId' => '22',
                        'defaultLanguage' => 'id',
                    ],
                    'status' => [
                        'privacyStatus' => 'public',
                        'selfDeclaredMadeForKids' => false,
                        'containsSyntheticMedia' => (bool) $post->media_ai,
                    ],
                ]);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('YouTube sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        $location = (string) $init->header('Location');

        if ($init->failed() || ! str_starts_with($location, 'https://www.googleapis.com/')) {
            throw $this->error($init->status(), (string) $init->json('error.message'));
        }

        $stream = $disk->readStream($file['path']);

        if (! is_resource($stream)) {
            throw new SocialException('File video tidak ditemukan di server.');
        }

        try {
            $upload = Http::timeout(900)->connectTimeout(15)->acceptJson()->withToken((string) $account->access_token)
                ->withBody(Utils::streamFor($stream), $mime)->put($location);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Unggah video ke YouTube terputus. Cek channel sebelum mencoba lagi.');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $id = (string) $upload->json('id');

        if ($upload->failed() || $id === '') {
            throw $this->error($upload->status(), (string) $upload->json('error.message'));
        }

        return ['status' => 'published', 'external_id' => $id, 'permalink' => 'https://www.youtube.com/shorts/'.rawurlencode($id)];
    }

    public function check(ContentPublication $publication, ContentPost $post, SocialAccount $account): array
    {
        return ['status' => 'published', 'external_id' => $publication->external_id, 'permalink' => $publication->permalink];
    }

    private function error(int $status, string $message): SocialException
    {
        report(new \RuntimeException('YouTube HTTP '.$status.': '.mb_substr($message, 0, 300)));

        return new SocialException('YouTube: '.match ($status) {
            401 => 'izin akun tidak berlaku lagi, sambungkan ulang.',
            403 => strip_tags($message ?: 'akses ditolak (kuota harian API habis atau izin kurang).'),
            default => strip_tags($message ?: 'gagal (HTTP '.$status.').'),
        }, $status === 401);
    }
}
