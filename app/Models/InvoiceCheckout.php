<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu kali bayar dari dashboard Tagihan (satu order Duitku) untuk beberapa invoice di satu branch. */
class InvoiceCheckout extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    // Uang masuk tapi sebagian invoice sudah lunas/dibatalkan lebih dulu: owner perlu refund bagian itu.
    public const STATUS_REFUND_NEEDED = 'refund_needed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu pembayaran',
        self::STATUS_PAID => 'Berhasil',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_REFUND_NEEDED => 'Perlu refund sebagian',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'late_fee' => 'integer', 'fee' => 'integer', 'net_amount' => 'integer',
            'expires_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceCheckoutItem::class, 'checkout_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Masih bisa dibayar di Duitku (belum lewat batas waktu). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
