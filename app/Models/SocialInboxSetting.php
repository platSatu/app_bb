<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Pengaturan Tools › Inbox (satu baris): mode AI dan token verifikasi webhook Meta. */
class SocialInboxSetting extends Model
{
    use HasUuids;

    /** mode => label */
    public const AI_MODES = [
        'off' => 'Nonaktif (admin membalas sendiri)',
        'suggest' => 'Draf saja (AI menulis, admin yang kirim)',
        'auto' => 'Otomatis (AI membalas pertanyaan yang aman)',
    ];

    /** Platform yang boleh dibalas AI otomatis. WhatsApp tetap memakai bot WhatsApp yang sudah ada. */
    public const AI_PLATFORMS = ['instagram', 'facebook', 'youtube'];

    public const DEFAULT_INSTRUCTIONS = "Kamu admin sosial media bizbos (app.bizbos.id), aplikasi untuk tempat kursus, bimbel, studio, dan usaha kecil: jadwal kelas, tagihan & link bayar, pengingat WhatsApp, Pages, tiket event.\n"
        ."bizbos bisa dicoba gratis di app.bizbos.id dan ada demo online 15 menit gratis.\n"
        .'Jangan menyebut harga, diskon, atau janji yang tidak tertulis di sini.';

    protected $fillable = ['ai_mode', 'ai_platforms', 'ai_instructions', 'ai_max_per_hour', 'updated_by'];

    protected $hidden = ['webhook_verify_token'];

    protected function casts(): array
    {
        return [
            'ai_platforms' => 'array',
            'ai_max_per_hour' => 'integer',
            'webhook_verify_token' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->first() ?? static::query()->create([
            'ai_mode' => 'off',
            'ai_platforms' => self::AI_PLATFORMS,
            'ai_instructions' => self::DEFAULT_INSTRUCTIONS,
        ]);

        if (blank($setting->webhook_verify_token)) {
            $setting->forceFill(['webhook_verify_token' => 'bzb_'.Str::random(40)])->save();
        }

        return $setting;
    }

    /** AI boleh membalas sendiri di platform ini. */
    public function autoFor(string $platform): bool
    {
        return $this->ai_mode === 'auto' && in_array($platform, (array) $this->ai_platforms, true);
    }

    public function aiEnabled(): bool
    {
        return in_array($this->ai_mode, ['suggest', 'auto'], true);
    }
}
