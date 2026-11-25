<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kredensial app developer marketplace (Lazada App Key/Secret, nanti TikTok & Shopee),
 * diisi superadmin. Boleh beberapa per channel; toko yang tersambung mencatat app-nya.
 */
class MarketplaceApp extends Model
{
    use HasActiveStatus, HasUuids;

    public const REGIONS = [
        'id' => 'Indonesia',
        'my' => 'Malaysia',
        'sg' => 'Singapore',
        'th' => 'Thailand',
        'vn' => 'Vietnam',
        'ph' => 'Philippines',
        'us' => 'United States',
        'gb' => 'United Kingdom',
    ];

    public const ENV_LIVE = 'live';
    public const ENV_SANDBOX = 'sandbox';

    public const ENVIRONMENTS = [
        self::ENV_LIVE => 'Live (produksi)',
        self::ENV_SANDBOX => 'Sandbox (uji coba)',
    ];

    /** Nama kolom kredensial per channel, untuk label form & tabel superadmin. */
    public const CREDENTIAL_LABELS = [
        'lazada' => ['key' => 'App Key', 'secret' => 'App Secret'],
        'shopee' => ['key' => 'Partner ID', 'secret' => 'Partner Key'],
        'tiktok' => ['key' => 'App Key', 'secret' => 'App Secret', 'service' => 'Service ID'],
    ];

    protected $fillable = ['channel', 'name', 'app_key', 'app_secret', 'service_id', 'callback_url', 'region', 'environment', 'is_default', 'status', 'notes'];

    protected $hidden = ['app_secret'];

    protected function casts(): array
    {
        return ['app_secret' => 'encrypted', 'is_default' => 'boolean'];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(MarketplaceStore::class, 'app_id');
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where($this->qualifyColumn('channel'), $channel);
    }

    /** App aktif untuk channel: yang ditandai default dulu, lalu yang paling lama. */
    public static function pickFor(string $channel, ?string $id = null): ?self
    {
        return static::forChannel($channel)->active()
            ->when($id, fn (Builder $q) => $q->whereKey($id))
            ->orderByDesc('is_default')->orderBy('created_at')
            ->first();
    }

    public function isSandbox(): bool
    {
        return $this->environment === self::ENV_SANDBOX;
    }

    public static function credentialLabel(string $channel, string $field): string
    {
        return self::CREDENTIAL_LABELS[$channel][$field] ?? ($field === 'key' ? 'App Key' : 'App Secret');
    }

    public function callbackUrl(): string
    {
        return $this->callback_url ?: route('marketplace.'.$this->channel.'.callback');
    }

    public function channelLabel(): string
    {
        return config("marketplace.channels.{$this->channel}.label", ucfirst($this->channel));
    }

    /** App Secret disamarkan untuk tampilan: abcd••••wxyz. */
    public function maskedSecret(): string
    {
        $secret = (string) $this->app_secret;

        return strlen($secret) <= 8 ? str_repeat('•', strlen($secret)) : substr($secret, 0, 4).str_repeat('•', 8).substr($secret, -4);
    }
}
