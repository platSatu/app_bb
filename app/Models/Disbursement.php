<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kiriman dana dari saldo branch ke satu rekening penerima, lewat Duitku Disbursement. */
class Disbursement extends Model
{
    use HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';

    // Batas wajar satu kiriman (Rp 1 miliar): menolak angka tidak masuk akal sebelum menyentuh saldo.
    public const MAX_AMOUNT = 1_000_000_000;

    public const SOURCE_DASHBOARD = 'dashboard';
    public const SOURCE_API = 'api';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    // Transfer sudah dikirim ke Duitku tapi hasilnya belum pasti: JANGAN dikembalikan otomatis, dicek tim bizbos.
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Antre',
        self::STATUS_PROCESSING => 'Diproses',
        self::STATUS_SUCCESS => 'Berhasil',
        self::STATUS_FAILED => 'Gagal (saldo dikembalikan)',
        self::STATUS_NEEDS_REVIEW => 'Sedang Dicek Tim bizbos',
    ];

    private const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-secondary-subtle text-secondary',
        self::STATUS_PROCESSING => 'bg-info-subtle text-info',
        self::STATUS_SUCCESS => 'bg-success-subtle text-success',
        self::STATUS_FAILED => 'bg-danger-subtle text-danger',
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
            'held_at' => 'datetime',
            'refunded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DisbursementRecipient::class, 'recipient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Hasil transfer belum pasti, atau proses terhenti di tengah jalan (mis. worker mati). */
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

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'net_amount' => $this->net_amount,
            'description' => $this->description,
            'payout_account' => [
                'id' => $this->recipient_id,
                'external_id' => $this->recipient?->external_id,
                'bank_code' => $this->bank_code,
                'bank_name' => $this->bank_name,
                'account_last4' => $this->account_last4,
                'account_name' => $this->account_name,
            ],
            'failure_reason' => $this->status === self::STATUS_FAILED ? $this->failure_reason : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}
