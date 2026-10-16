<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawal extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    // Transfer sudah dikirim ke Duitku tapi hasilnya belum pasti: JANGAN dikembalikan otomatis, admin cek dulu.
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_SUCCESS => 'Berhasil',
        self::STATUS_FAILED => 'Gagal (saldo dikembalikan)',
        self::STATUS_REJECTED => 'Ditolak (saldo dikembalikan)',
        self::STATUS_CANCELLED => 'Dibatalkan (saldo dikembalikan)',
        self::STATUS_NEEDS_REVIEW => 'Sedang Dicek Admin',
    ];

    private const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning',
        self::STATUS_PROCESSING => 'bg-info-subtle text-info',
        self::STATUS_SUCCESS => 'bg-success-subtle text-success',
        self::STATUS_FAILED => 'bg-danger-subtle text-danger',
        self::STATUS_REJECTED => 'bg-danger-subtle text-danger',
        self::STATUS_CANCELLED => 'bg-secondary-subtle text-secondary',
        self::STATUS_NEEDS_REVIEW => 'bg-warning-subtle text-warning',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'net_amount' => 'integer',
            'duitku_response' => 'array',
            'approved_at' => 'datetime',
            'held_at' => 'datetime',
            'refunded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** Hasil transfer belum pasti, atau proses terhenti di tengah jalan (mis. server timeout). */
    public function needsResolve(): bool
    {
        return $this->status === self::STATUS_NEEDS_REVIEW
            || ($this->status === self::STATUS_PROCESSING && $this->updated_at?->lt(now()->subMinutes(10)));
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
