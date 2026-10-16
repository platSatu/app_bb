<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Pembayaran',
        self::STATUS_SUCCESS => 'Berhasil',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'bg-success-subtle text-success',
            self::STATUS_PENDING => 'bg-warning-subtle text-warning',
            default => 'bg-danger-subtle text-danger',
        };
    }
}
