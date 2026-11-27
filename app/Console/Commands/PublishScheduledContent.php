<?php

namespace App\Console\Commands;

use App\Jobs\GenerateContentMedia;
use App\Jobs\PublishContent;
use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Services\Marketing\ContentMedia;
use Illuminate\Console\Command;

/**
 * Tools › Konten Harian, dijadwalkan tiap menit:
 *  1. Konten yang jadwalnya tiba -> antrekan kiriman ke tiap akun.
 *  2. Kiriman yang sedang diproses platform -> cek statusnya.
 *  3. Kiriman yang macet -> ditandai gagal (TIDAK diulang otomatis supaya tidak posting dobel).
 *  4. Video yang sedang dibuat Classai -> cek; konten disetujui tanpa media -> dibuatkan sesuai kuota harian.
 */
class PublishScheduledContent extends Command
{
    protected $signature = 'content:publish';

    protected $description = 'Kirim konten sosmed yang jadwalnya tiba, cek status kiriman & pembuatan media';

    /** Kiriman "publishing" lebih lama dari ini = worker berhenti di tengah jalan. */
    private const STALE_PUBLISHING_MINUTES = 20;

    /** Platform belum selesai memproses setelah ini = dianggap gagal (cek manual di akunnya). */
    private const PROCESSING_LIMIT_MINUTES = 120;

    public function handle(ContentMedia $media): int
    {
        $due = ContentPublication::where('status', 'pending')
            ->whereHas('post', fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '<=', now()))
            ->orderBy('created_at')->limit(20)->pluck('id');

        foreach ($due as $id) {
            PublishContent::dispatch($id, 'start');
        }

        $checks = ContentPublication::where('status', 'processing')->where('updated_at', '<=', now()->subSeconds(30))
            ->where('started_at', '>', now()->subMinutes(self::PROCESSING_LIMIT_MINUTES))->limit(30)->pluck('id');

        foreach ($checks as $id) {
            PublishContent::dispatch($id, 'check');
        }

        $this->expire('publishing', self::STALE_PUBLISHING_MINUTES, 'Pengiriman terhenti di tengah jalan. Cek akun tujuan dulu (mungkin sudah terposting), lalu tekan Coba lagi bila belum.');
        $this->expire('processing', self::PROCESSING_LIMIT_MINUTES, 'Platform belum selesai memproses dalam 2 jam. Cek langsung di akun tujuan.');

        $videos = ContentPost::where('media_status', 'processing')->whereNotNull('media_job_id')
            ->where('updated_at', '<=', now()->subSeconds(30))->limit(10)->pluck('id');

        foreach ($videos as $id) {
            GenerateContentMedia::dispatch($id);
        }

        // Gambar/carousel yang "sedang dibuat" lebih dari 30 menit = worker berhenti di tengah jalan.
        ContentPost::where('media_status', 'processing')->whereNull('media_job_id')->where('media_started_at', '<', now()->subMinutes(30))->limit(20)->get()
            ->each(fn (ContentPost $post) => $post->forceFill([
                'media_status' => $post->mediaFiles() !== [] ? 'done' : 'failed',
                'media_error' => 'Pembuatan media terhenti di tengah jalan. Klik Buat media untuk mencoba lagi.',
            ])->save());

        // Antrean yang tidak pernah diambil worker (mis. worker mati) dilepas setelah 30 menit supaya kuota harian tidak terkunci.
        ContentPost::where('media_status', 'queued')->where('updated_at', '<', now()->subMinutes(30))->limit(20)->get()
            ->each(fn (ContentPost $post) => $post->forceFill([
                'media_status' => $post->mediaFiles() !== [] ? 'done' : null,
                'media_error' => 'Antrean pembuatan media tidak berjalan. Pastikan queue worker hidup (Settings › Antrean & Tugas).',
            ])->save());

        $queued = $media->autoQueue();

        $this->info("Dikirim: {$due->count()}, dicek: {$checks->count()}, video dicek: {$videos->count()}, media baru: {$queued}.");

        return self::SUCCESS;
    }

    private function expire(string $status, int $minutes, string $reason): void
    {
        $column = $status === 'publishing' ? 'updated_at' : 'started_at';

        ContentPublication::with('post')->where('status', $status)->where($column, '<', now()->subMinutes($minutes))->limit(50)->get()
            ->each(function (ContentPublication $publication) use ($status, $reason) {
                if ($publication->claim($status, 'failed')) {
                    $publication->forceFill(['error' => $reason])->save();
                    $publication->post?->refreshPublishStatus();
                }
            });
    }
}
