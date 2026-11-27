<?php

namespace App\Services\Marketing;

use App\Jobs\GenerateContentMedia;
use App\Models\AiProvider;
use App\Models\ContentAgentSetting;
use App\Models\ContentPost;
use App\Services\Ai\ClassaiMedia;
use App\Services\Ai\MediaCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Media untuk konten yang sudah disetujui (Tools › Konten Harian), lewat Classai Router (lihat ClassaiMedia):
 *  - image:    1 gambar 4:5 (feed Instagram/Facebook/TikTok foto).
 *  - carousel: 1 gambar 4:5 per slide (maks 10).
 *  - reel:     1 klip video 9:16 (tugas Classai; dicek tiap menit oleh content:publish sampai jadi).
 * Gambar selalu disimpan sebagai JPEG (Instagram hanya menerima JPEG). File di disk "local" (privat); platform sosmed
 * membacanya lewat link bertanda tangan yang berlaku beberapa jam (ContentMediaController::public).
 * Batas media per hari (Pengaturan) menahan biaya kalau banyak konten disetujui sekaligus.
 */
class ContentMedia
{
    public const DIR = 'content-media';

    public const VIDEO_LIMIT_MINUTES = 45;

    /** Sisi terpanjang gambar (px) & batas ukuran Instagram. */
    private const MAX_SIDE = 2160;

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly ClassaiMedia $media, private readonly MediaCatalog $catalog) {}

    /** Penyedia AI aktif yang bisa membuat media (hanya Classai Router). */
    public function provider(): ?AiProvider
    {
        $provider = AiProvider::active();

        return $provider?->isClassai() ? $provider : null;
    }

    /** Sisa kuota media hari ini (yang sudah mulai dibuat + yang sedang antre ikut dihitung). */
    public function remainingToday(?ContentAgentSetting $setting = null): int
    {
        $setting ??= ContentAgentSetting::current();
        $used = ContentPost::where('media_started_at', '>=', today())->count() + ContentPost::where('media_status', 'queued')->count();

        return max(0, $setting->media_daily_limit - $used);
    }

    /**
     * Antrekan pembuatan media satu konten. Kembalikan null kalau berhasil, atau alasan kalau tidak bisa.
     */
    public function queue(ContentPost $post): ?string
    {
        if (! in_array($post->status, ['approved', 'ready'], true)) {
            return 'Setujui kontennya dulu sebelum membuat media.';
        }

        if (in_array($post->media_status, ['queued', 'processing'], true)) {
            return 'Media konten ini sedang dibuat.';
        }

        if (! $this->provider()) {
            return 'Pembuatan media butuh penyedia AI Classai Router yang aktif (Settings › AI).';
        }

        if ($this->remainingToday() <= 0) {
            return 'Batas pembuatan media hari ini sudah tercapai (lihat Pengaturan). Sisanya dibuat besok.';
        }

        $updated = ContentPost::whereKey($post->getKey())->where(fn ($q) => $q->whereNull('media_status')->orWhereIn('media_status', ['done', 'failed']))
            ->update(['media_status' => 'queued', 'media_error' => null, 'updated_at' => now()]);

        if ($updated !== 1) {
            return 'Media konten ini sedang dibuat.';
        }

        GenerateContentMedia::dispatch($post->getKey());

        return null;
    }

    /** Konten disetujui yang belum punya media: antrekan sedikit demi sedikit sesuai kuota (dipanggil tiap menit). */
    public function autoQueue(int $max = 3): int
    {
        $setting = ContentAgentSetting::current();

        if (! $setting->auto_media || ! $this->provider()) {
            return 0;
        }

        $room = min($max, $this->remainingToday($setting));
        $count = 0;

        if ($room <= 0) {
            return 0;
        }

        foreach (ContentPost::where('status', 'approved')->whereNull('media_status')->orderBy('publish_date')->orderBy('publish_time')->limit($room)->get() as $post) {
            $count += $this->queue($post) === null ? 1 : 0;
        }

        return $count;
    }

    /** Dikerjakan GenerateContentMedia: gambar langsung jadi; video dimulai lalu dicek berkala (poll). */
    public function run(ContentPost $post): void
    {
        $provider = $this->provider();

        if (! $provider) {
            $this->fail($post, 'Penyedia AI yang aktif bukan Classai Router.');

            return;
        }

        if ($post->media_status === 'processing' && $post->isVideo() && $post->media_job_id) {
            $this->pollVideo($post, $provider);

            return;
        }

        $claimed = ContentPost::whereKey($post->getKey())->where('media_status', 'queued')
            ->update(['media_status' => 'processing', 'media_started_at' => now(), 'media_job_id' => null, 'updated_at' => now()]) === 1;

        if (! $claimed) {
            return;
        }

        $post->refresh();
        $setting = ContentAgentSetting::current();

        try {
            $post->isVideo() ? $this->startVideo($post, $provider, $setting) : $this->images($post, $provider, $setting);
        } catch (RuntimeException $e) {
            $this->fail($post, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->fail($post, 'Terjadi kesalahan saat membuat media. Coba lagi.');
        }
    }

    /**
     * Ganti media dengan file sendiri (mis. video hasil edit). Gambar diubah ke JPEG.
     *
     * @param  list<UploadedFile>  $files
     */
    public function replaceWithUpload(ContentPost $post, array $files): void
    {
        $stored = [];

        foreach ($files as $file) {
            $bytes = (string) file_get_contents($file->getRealPath());
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

            if (str_starts_with($mime, 'image/')) {
                $stored[] = $this->store($post, $this->jpeg($bytes), 'image/jpeg', 'jpg');
            } elseif ($mime === 'video/mp4' || $mime === 'video/quicktime') {
                $stored[] = $this->store($post, $bytes, $mime, $mime === 'video/mp4' ? 'mp4' : 'mov');
            } else {
                throw new RuntimeException('File '.$file->getClientOriginalName().' bukan gambar atau video MP4/MOV.');
            }
        }

        $this->finish($post, $stored, null, false);
    }

    public static function deleteFiles(array $media): void
    {
        foreach ($media as $file) {
            if (is_array($file) && is_string($file['path'] ?? null) && str_starts_with($file['path'], self::DIR.'/')) {
                Storage::disk(ClassaiMedia::DISK)->delete($file['path']);
            }
        }
    }

    /** Perkiraan harga satu media dengan pengaturan sekarang (teks dari katalog Classai). */
    public function priceHint(ContentAgentSetting $setting, string $format): ?string
    {
        if ($format !== 'reel') {
            $price = collect($this->catalog->images())->firstWhere('id', $setting->imageModel())['price'] ?? null;

            return $price ? $price.($format === 'carousel' ? ' × '.max(3, min(ContentPost::MAX_SLIDES, $setting->carousel_slides)).' slide' : '') : null;
        }

        $model = collect($this->catalog->videos())->firstWhere('id', $setting->videoModel());
        $perSecond = $model['price_per_second'][$setting->videoResolution()] ?? null;

        return $perSecond ? '±Rp'.number_format($perSecond * $setting->video_seconds, 0, ',', '.') : null;
    }

    private function images(ContentPost $post, AiProvider $provider, ContentAgentSetting $setting): void
    {
        $model = $this->catalog->find($provider, 'image', $setting->imageModel());

        if (! $model) {
            throw new RuntimeException('Model gambar '.$setting->imageModel().' tidak tersedia di Classai. Ganti di Pengaturan.');
        }

        $aspect = in_array('4:5', $model['aspects'], true) ? '4:5' : '1:1';
        $stored = [];
        $cost = 0;

        try {
            foreach ($this->imagePrompts($post, $setting) as $prompt) {
                $result = $this->media->image($provider, $model['id'], ['prompt' => $prompt, 'aspect_ratio' => $aspect, 'n' => 1]);
                $stored[] = $this->store($post, $this->jpeg($result['files'][0]['bytes']), 'image/jpeg', 'jpg');
                $cost += (int) $result['cost_micro_rp'];
            }
        } catch (Throwable $e) {
            self::deleteFiles($stored); // carousel setengah jadi tidak disimpan

            throw $e;
        }

        $this->finish($post, $stored, $cost ?: null, true);
    }

    private function startVideo(ContentPost $post, AiProvider $provider, ContentAgentSetting $setting): void
    {
        $model = $this->catalog->find($provider, 'video', $setting->videoModel());

        if (! $model || ! in_array('9:16', $model['aspects'], true)) {
            throw new RuntimeException('Model video '.$setting->videoModel().' tidak tersedia atau tidak mendukung 9:16. Ganti di Pengaturan.');
        }

        $seconds = $setting->video_seconds;
        $range = $model['seconds'];
        $seconds = isset($range['list'])
            ? collect($range['list'])->sortBy(fn ($s) => abs($s - $seconds))->first()
            : max($range['min'], min($range['max'], $seconds));
        $resolution = in_array($setting->videoResolution(), $model['resolutions'], true) ? $setting->videoResolution() : ($model['resolutions'][0] ?? null);

        $jobId = $this->media->startVideo($provider, $model['id'], [
            'prompt' => $this->videoPrompt($post, $setting), 'aspect_ratio' => '9:16', 'resolution' => $resolution, 'seconds' => $seconds,
        ]);

        $post->forceFill(['media_job_id' => $jobId])->save();
    }

    private function pollVideo(ContentPost $post, AiProvider $provider): void
    {
        Cache::lock('content-media-video:'.$post->getKey(), 320)->get(function () use ($post, $provider) {
            $post->refresh();

            if ($post->media_status !== 'processing' || ! $post->media_job_id) {
                return;
            }

            $tooLong = $post->media_started_at?->lt(now()->subMinutes(self::VIDEO_LIMIT_MINUTES)) ?? false;

            try {
                $state = $this->media->videoStatus($provider, $post->media_job_id);
            } catch (RuntimeException) {
                $tooLong ? $this->fail($post, 'Video tidak selesai dalam '.self::VIDEO_LIMIT_MINUTES.' menit.') : $post->touch();

                return;
            }

            if ($state['status'] === 'failed') {
                $this->fail($post, 'Video gagal dibuat Classai'.($state['error'] ? ': '.$state['error'] : '.'));

                return;
            }

            if ($state['status'] !== 'completed') {
                $tooLong ? $this->fail($post, 'Video tidak selesai dalam '.self::VIDEO_LIMIT_MINUTES.' menit.') : $post->touch();

                return;
            }

            try {
                $file = $this->media->downloadVideo($provider, $post->media_job_id);
            } catch (RuntimeException $e) {
                $tooLong ? $this->fail($post, $e->getMessage()) : $post->touch();

                return;
            }

            $this->finish($post, [$this->store($post, $file['bytes'], $file['mime'], $file['ext'])], null, true);
        });
    }

    /** @return list<string> satu prompt per gambar */
    private function imagePrompts(ContentPost $post, ContentAgentSetting $setting): array
    {
        $style = Str::limit((string) ($setting->visual_style ?: ContentAgentSetting::DEFAULT_VISUAL), 500, '');
        $visual = Str::limit((string) ($post->visual_prompt ?: $post->title), 1200, '');

        if ($post->format !== 'carousel') {
            $headline = Str::limit(Str::squish((string) (strtok((string) $post->script, "\n") ?: $post->hook ?: $post->title)), 90, '');

            return [$visual.'. Style: '.$style.'. Social media feed post, portrait. Render this short Indonesian headline clearly, '
                .'large and spelled exactly: "'.$headline.'". No other text, no logos, no watermark.'];
        }

        $slides = collect((array) $post->slides)->filter(fn ($s) => is_array($s))->take(ContentPost::MAX_SLIDES)->values();

        if ($slides->isEmpty()) {
            throw new RuntimeException('Carousel ini belum punya slide. Isi slide dulu (Edit), lalu buat media lagi.');
        }

        return $slides->map(fn ($s, $i) => $visual.'. Style: '.$style.'. Carousel slide '.($i + 1).' of '.$slides->count()
            .', same consistent layout, colors and typography on every slide, portrait. Render this Indonesian text clearly and spelled exactly: '
            .'title "'.Str::limit(Str::squish((string) ($s['heading'] ?? '')), 70, '').'"'
            .(filled($s['body'] ?? null) ? ' and smaller text "'.Str::limit(Str::squish((string) $s['body']), 140, '').'"' : '')
            .'. No other text, no logos, no watermark.')->all();
    }

    private function videoPrompt(ContentPost $post, ContentAgentSetting $setting): string
    {
        return Str::limit((string) ($post->visual_prompt ?: $post->title), 1500, '').'. Style: '
            .Str::limit((string) ($setting->visual_style ?: ContentAgentSetting::DEFAULT_VISUAL), 500, '')
            .'. Vertical 9:16 short social media video, smooth camera movement, natural lighting, no text, no logos, no watermark.';
    }

    /** Gambar -> JPEG (latar putih untuk bagian transparan), sisi terpanjang maks MAX_SIDE. Butuh ekstensi GD. */
    private function jpeg(string $bytes): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! function_exists('imagecreatefromstring')) {
            if ($mime === 'image/jpeg' && strlen($bytes) <= self::MAX_IMAGE_BYTES) {
                return $bytes;
            }

            throw new RuntimeException('Ekstensi PHP GD belum aktif di server, gambar tidak bisa diubah ke JPEG untuk Instagram.');
        }

        $src = @imagecreatefromstring($bytes);

        if (! $src) {
            throw new RuntimeException('Gambar tidak bisa dibaca.');
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        if ($mime === 'image/jpeg' && $scale >= 1 && strlen($bytes) <= self::MAX_IMAGE_BYTES) {
            return $bytes;
        }

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 88);
        $out = (string) ob_get_clean();
        unset($src, $dst);

        if ($out === '' || strlen($out) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Gambar terlalu besar setelah diubah ke JPEG.');
        }

        return $out;
    }

    private function store(ContentPost $post, string $bytes, string $mime, string $ext): array
    {
        $path = self::DIR.'/'.$post->getKey().'/'.Str::uuid().'.'.$ext;
        Storage::disk(ClassaiMedia::DISK)->put($path, $bytes);

        return ['path' => $path, 'mime' => $mime, 'size' => strlen($bytes)];
    }

    private function finish(ContentPost $post, array $stored, ?int $cost, bool $ai): void
    {
        $old = $post->mediaFiles();

        $post->forceFill([
            'media' => $stored,
            'media_status' => 'done',
            'media_ai' => $ai,
            'media_error' => null,
            'media_job_id' => null,
            'media_cost_micro_rp' => $cost,
            'status' => $post->status === 'approved' ? 'ready' : $post->status,
        ])->save();

        self::deleteFiles(array_filter($old, fn ($f) => ! in_array($f['path'] ?? null, array_column($stored, 'path'), true)));
    }

    private function fail(ContentPost $post, string $reason): void
    {
        // Media lama (kalau ini pembuatan ulang) tetap dipakai: status kembali "done" bila masih ada file.
        $post->forceFill([
            'media_status' => $post->mediaFiles() !== [] ? 'done' : 'failed',
            'media_error' => mb_substr($reason, 0, 300),
            'media_job_id' => null,
        ])->save();
    }
}
