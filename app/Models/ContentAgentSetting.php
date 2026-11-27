<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan Agent Konten (satu baris). Lihat App\Services\Marketing\ContentAgent & ContentMedia. */
class ContentAgentSetting extends Model
{
    use HasUuids;

    public const DEFAULT_PILLARS = [
        ['name' => 'Tips usaha kursus', 'description' => 'Tips praktis mengelola tempat kursus/les/bimbel/studio: jadwal, murid, tagihan, promosi.'],
        ['name' => 'Fitur bizbos', 'description' => 'Cara memakai satu fitur bizbos (jadwal kelas, tagihan & link bayar, pengingat WhatsApp, Pages, tiket event) dan manfaatnya.'],
        ['name' => 'Masalah sehari-hari', 'description' => 'Masalah umum pemilik usaha (murid lupa jadwal, telat bayar, rekap manual) dan cara mengatasinya.'],
        ['name' => 'Edukasi & motivasi', 'description' => 'Wawasan bisnis kecil, digitalisasi usaha, dan semangat untuk pemilik usaha.'],
        ['name' => 'Ajakan coba', 'description' => 'Ajakan mencoba bizbos gratis atau demo online, tanpa harga atau diskon karangan.'],
    ];

    public const DEFAULT_VOICE = 'Ramah, jelas, dan membantu. Bahasa Indonesia santai tapi sopan (pakai "kamu"). Kalimat pendek, tanpa jargon.';

    public const DEFAULT_AUDIENCE = 'Pemilik dan admin tempat kursus musik, kursus bahasa, bimbel, studio yoga/dance, sanggar, dan usaha kecil dengan murid/anggota tetap di Indonesia.';

    public const DEFAULT_OFFER = 'bizbos bisa dicoba gratis di app.bizbos.id. Tersedia demo online 15 menit gratis. Jangan menyebut harga, diskon, atau janji lain.';

    public const DEFAULT_VISUAL = 'Desain modern dan bersih, warna navy #18223B, oranye #FF8A3D, krem #FFF4E6, suasana tempat kursus di Indonesia, orang Indonesia.';

    public const DEFAULT_FORMATS = ['reel' => 40, 'carousel' => 40, 'image' => 20];

    public const DEFAULT_TIMES = ['11:30', '19:00'];

    /** Model media bawaan (Classai Router). Bisa diganti di Pengaturan. */
    public const DEFAULT_IMAGE_MODEL = 'nano-banana-2.1';

    public const DEFAULT_VIDEO_MODEL = 'oxygen-1.0';

    public const DEFAULT_VIDEO_RESOLUTION = '768p';

    protected $fillable = [
        'pillars', 'brand_voice', 'audience', 'offer', 'visual_style', 'formats', 'post_times', 'auto_media',
        'image_model', 'video_model', 'video_resolution', 'video_seconds', 'carousel_slides', 'media_daily_limit',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'pillars' => 'array',
            'formats' => 'array',
            'post_times' => 'array',
            'auto_media' => 'boolean',
            'video_seconds' => 'integer',
            'carousel_slides' => 'integer',
            'media_daily_limit' => 'integer',
            'plan_state' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'pillars' => self::DEFAULT_PILLARS,
            'brand_voice' => self::DEFAULT_VOICE,
            'audience' => self::DEFAULT_AUDIENCE,
            'offer' => self::DEFAULT_OFFER,
            'visual_style' => self::DEFAULT_VISUAL,
            'formats' => self::DEFAULT_FORMATS,
            'post_times' => self::DEFAULT_TIMES,
            'image_model' => self::DEFAULT_IMAGE_MODEL,
            'video_model' => self::DEFAULT_VIDEO_MODEL,
            'video_resolution' => self::DEFAULT_VIDEO_RESOLUTION,
        ])->refresh();
    }

    /** @return list<array{name: string, description: string}> */
    public function pillarList(): array
    {
        return array_values(array_filter($this->pillars ?: self::DEFAULT_PILLARS, fn ($p) => filled($p['name'] ?? null)));
    }

    /** Pilar dalam format isian: satu baris "Nama: keterangan". */
    public function pillarsText(): string
    {
        return collect($this->pillarList())->map(fn ($p) => $p['name'].': '.($p['description'] ?? ''))->implode("\n");
    }

    /** @return list<string> jam tayang "HH:MM", urut. */
    public function times(): array
    {
        $times = array_values(array_filter((array) $this->post_times, fn ($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)));
        sort($times);

        return $times ?: self::DEFAULT_TIMES;
    }

    /** @return array<string, int> persen per format (total > 0). */
    public function formatMix(): array
    {
        $mix = collect(ContentPost::FORMATS)->keys()->mapWithKeys(fn ($f) => [$f => max(0, (int) (($this->formats ?? [])[$f] ?? 0))])->all();

        return array_sum($mix) > 0 ? $mix : self::DEFAULT_FORMATS;
    }

    public function imageModel(): string
    {
        return $this->image_model ?: self::DEFAULT_IMAGE_MODEL;
    }

    public function videoModel(): string
    {
        return $this->video_model ?: self::DEFAULT_VIDEO_MODEL;
    }

    public function videoResolution(): string
    {
        return $this->video_resolution ?: self::DEFAULT_VIDEO_RESOLUTION;
    }

    /** Pembuatan rencana sedang berjalan (dianggap macet kalau tidak ada kemajuan 15 menit). */
    public function planRunning(): bool
    {
        $state = $this->plan_state ?? [];

        return ! empty($state['running']) && isset($state['updated_at']) && now()->subMinutes(15)->lt(\Illuminate\Support\Carbon::parse($state['updated_at']));
    }
}
