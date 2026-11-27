<?php

namespace App\Jobs;

use App\Models\ContentPost;
use App\Services\Marketing\ContentMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Buat media satu konten (Tools › Konten Harian). Gambar/carousel selesai dalam satu jalan; video dimulai di sini
 * lalu dicek tiap menit oleh perintah content:publish sampai jadi. Status "queued -> processing" diklaim atomik,
 * jadi job yang terjalan dua kali tidak memanggil AI dua kali.
 */
class GenerateContentMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Carousel 10 slide x model lambat masih muat. */
    public int $timeout = 900;

    public function __construct(public string $postId)
    {
        $this->afterCommit();
    }

    public function handle(ContentMedia $media): void
    {
        if ($post = ContentPost::find($this->postId)) {
            $media->run($post);
        }
    }
}
