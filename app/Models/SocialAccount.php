<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun sosmed yang tersambung (Tools › Akun Sosmed). Token disimpan terenkripsi dan tidak pernah dikirim ke browser.
 * Instagram & Facebook Page memakai Page access token (tidak kedaluwarsa selama izin tidak dicabut);
 * TikTok & YouTube memakai access token pendek yang diperpanjang dengan refresh token.
 */
class SocialAccount extends Model
{
    use HasUuids;

    /** platform => [label, ikon, warna] */
    public const PLATFORMS = [
        'instagram' => ['Instagram', 'ri-instagram-line', '#E1306C'],
        'facebook' => ['Facebook Page', 'ri-facebook-circle-line', '#1877F2'],
        'tiktok' => ['TikTok', 'ri-tiktok-line', '#000000'],
        'youtube' => ['YouTube', 'ri-youtube-line', '#FF0000'],
    ];

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ERROR = 'error';

    protected $fillable = ['platform', 'external_id', 'name', 'username', 'avatar_url', 'is_default'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'meta' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function publications(): HasMany
    {
        return $this->hasMany(ContentPublication::class);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), self::STATUS_CONNECTED);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform][0] ?? $this->platform;
    }

    public function platformIcon(): string
    {
        return self::PLATFORMS[$this->platform][1] ?? 'ri-share-line';
    }

    public function platformColor(): string
    {
        return self::PLATFORMS[$this->platform][2] ?? '#6c757d';
    }

    public function displayName(): string
    {
        return $this->name.($this->username ? ' (@'.ltrim($this->username, '@').')' : '');
    }

    /** Access token perlu diperpanjang (sisa kurang dari $minutes menit). Token tanpa masa berlaku tidak perlu. */
    public function needsRefresh(int $minutes = 10): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->lt(now()->addMinutes($minutes));
    }

    /** Token ditolak platform: akun perlu disambungkan ulang. */
    public function markExpired(string $reason): void
    {
        $this->forceFill(['status' => self::STATUS_EXPIRED, 'status_reason' => mb_substr($reason, 0, 300)])->save();
    }

    /**
     * Simpan / perbarui akun hasil OAuth (kunci: platform + ID akun di platform). Pilihan "default" yang sudah diatur
     * tidak ditimpa; akun baru langsung jadi tujuan bawaan.
     *
     * @param  array{name: string, username?: ?string, avatar_url?: ?string, access_token: string, refresh_token?: ?string,
     *     token_expires_at?: mixed, refresh_expires_at?: mixed, meta?: array}  $data
     */
    public static function remember(string $platform, string $externalId, array $data, User $user): self
    {
        $account = static::where('platform', $platform)->where('external_id', $externalId)->first()
            ?? (new static())->forceFill(['platform' => $platform, 'external_id' => $externalId, 'is_default' => true]);

        $account->forceFill([
            'name' => mb_substr(strip_tags($data['name'] ?: $externalId), 0, 200),
            'username' => isset($data['username']) ? mb_substr(strip_tags((string) $data['username']), 0, 200) : $account->username,
            'avatar_url' => isset($data['avatar_url']) && str_starts_with((string) $data['avatar_url'], 'https://') ? $data['avatar_url'] : $account->avatar_url,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => $data['token_expires_at'] ?? null,
            'refresh_expires_at' => $data['refresh_expires_at'] ?? $account->refresh_expires_at,
            'meta' => array_merge($account->meta ?? [], $data['meta'] ?? []),
            'status' => self::STATUS_CONNECTED,
            'status_reason' => null,
            'connected_by' => $user->getKey(),
        ])->save();

        return $account;
    }

    /** @return list<string> pilihan privasi TikTok untuk akun ini (dari creator_info). */
    public function tiktokPrivacyOptions(): array
    {
        return array_values(array_filter((array) (($this->meta ?? [])['privacy_level_options'] ?? []), 'is_string'));
    }
}
