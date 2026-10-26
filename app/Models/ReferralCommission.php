<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komisi pengajak dari satu pembelian paket oleh orang yang diajak. Persen & nominal disalin saat beli,
 * jadi perubahan pengaturan sesudahnya tidak mengubah komisi yang sudah tercatat.
 */
class ReferralCommission extends Model
{
    use HasUuids;

    public const STATUS_HELD = 'held';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_HELD => 'Ditahan',
        self::STATUS_RELEASED => 'Cair',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const STATUS_BADGES = [
        self::STATUS_HELD => 'bg-warning-subtle text-warning',
        self::STATUS_RELEASED => 'bg-success-subtle text-success',
        self::STATUS_CANCELLED => 'bg-danger-subtle text-danger',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'discount_amount' => 'integer',
            'paid_amount' => 'integer',
            'commission_percent' => 'integer',
            'share_percent' => 'integer',
            'amount' => 'integer',
            'release_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-light text-dark';
    }
}
