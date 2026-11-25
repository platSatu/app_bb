<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Toko marketplace yang sudah diotorisasi seller, tersambung ke satu page Catalog Online. */
class MarketplaceStore extends Model
{
    use HasUuids;

    public const STATUS_CONNECTED = 'connected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ERROR = 'error';

    public const STATUS_LABELS = [
        self::STATUS_CONNECTED => 'Tersambung',
        self::STATUS_EXPIRED => 'Perlu otorisasi ulang',
        self::STATUS_ERROR => 'Bermasalah',
    ];

    // page_id, branch_id, app_id, channel & token diisi sistem saat otorisasi.
    protected $fillable = ['name'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MarketplaceApp::class, 'app_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'store_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class, 'store_id');
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && ! $this->accessExpired();
    }

    public function accessExpired(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    /** Token akses perlu diperpanjang (habis dalam $hours jam ke depan). */
    public function needsRefresh(int $hours = 48): bool
    {
        return $this->access_expires_at === null || $this->access_expires_at->lt(now()->addHours($hours));
    }

    public function canRefresh(): bool
    {
        return $this->refresh_token && ($this->refresh_expires_at === null || $this->refresh_expires_at->isFuture());
    }

    public function channelLabel(): string
    {
        return config("marketplace.channels.{$this->channel}.label", ucfirst($this->channel));
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->account ?: $this->channelLabel().' '.$this->external_id);
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_CONNECTED && $this->accessExpired()) {
            return 'Token habis';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return $this->isConnected() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
    }
}
