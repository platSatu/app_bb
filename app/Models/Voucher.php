<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Didapat saat user membeli package. Selama belum di-redeem, kodenya boleh
 * diberikan ke orang lain; user_id = pembeli, redeemed_by = yang memakai.
 */
class Voucher extends Model
{
    use HasUuids;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Belum Dipakai',
        self::STATUS_REDEEMED => 'Sudah Dipakai',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
    ];

    private const STATUS_BADGES = [
        self::STATUS_AVAILABLE => 'bg-success-subtle text-success',
        self::STATUS_REDEEMED => 'bg-secondary-subtle text-secondary',
        self::STATUS_EXPIRED => 'bg-danger-subtle text-danger',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['price' => 'integer', 'valid_from' => 'datetime', 'valid_until' => 'datetime', 'redeemed_at' => 'datetime'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            self::STATUS_REDEEMED => $query->whereNotNull('redeemed_at'),
            self::STATUS_EXPIRED => $query->whereNull('redeemed_at')->where('valid_until', '<', now()),
            self::STATUS_AVAILABLE => $query->whereNull('redeemed_at')->where('valid_until', '>=', now()),
            default => $query,
        };
    }

    public function status(): string
    {
        return match (true) {
            $this->redeemed_at !== null => self::STATUS_REDEEMED,
            $this->valid_until->isPast() => self::STATUS_EXPIRED,
            default => self::STATUS_AVAILABLE,
        };
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status()];
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status()];
    }
}
