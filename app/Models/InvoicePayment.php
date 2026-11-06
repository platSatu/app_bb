<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu percobaan bayar (satu order Duitku). */
class InvoicePayment extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    // Uang masuk tapi invoice sudah lunas/dibatalkan lebih dulu: saldo tetap dicatat, owner perlu refund ke customer.
    public const STATUS_REFUND_NEEDED = 'refund_needed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_PAID => 'Berhasil',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_REFUND_NEEDED => 'Perlu Refund',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'late_fee' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
