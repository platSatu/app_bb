<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Services\Ai\ClassaiMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File media konten untuk diambil platform sosmed (Instagram, Facebook, TikTok foto) saat posting.
 * Publik tapi hanya lewat link bertanda tangan yang berlaku beberapa jam (lihat SocialManager::mediaUrls),
 * dan hanya untuk konten yang sudah dijadwalkan / diposting. Draf tidak pernah bisa dibuka dari luar.
 */
class ContentMediaController extends Controller
{
    public function __invoke(ContentPost $post, string $file): StreamedResponse
    {
        abort_unless(preg_match('/^(\d{1,2})\.(jpg|png|webp|mp4|webm|mov)$/', $file, $m), 404);
        abort_unless(in_array($post->status, ['scheduled', 'published', 'partial', 'failed'], true), 404);

        $media = $post->mediaFiles()[(int) $m[1]] ?? abort(404);
        abort_unless(pathinfo($media['path'], PATHINFO_EXTENSION) === $m[2], 404);

        $disk = Storage::disk(ClassaiMedia::DISK);
        abort_unless($disk->exists($media['path']), 404);

        return $disk->response($media['path'], $file, [
            'Content-Type' => $media['mime'],
            'Content-Length' => (string) $disk->size($media['path']),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
