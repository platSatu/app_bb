<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketOrder extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    // Dibayar setelah kedaluwarsa tapi kuota sudah habis: uang masuk, tiket tidak dibuat, owner perlu refund.
    public const STATUS_REFUND_NEEDED = 'refund_needed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Bayar',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_REFUND_NEEDED => 'Perlu Refund',
    ];

    private const STATUS_BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning',
        self::STATUS_PAID => 'bg-success-subtle text-success',
        self::STATUS_EXPIRED => 'bg-secondary-subtle text-secondary',
        self::STATUS_FAILED => 'bg-danger-subtle text-danger',
        self::STATUS_REFUND_NEEDED => 'bg-danger-subtle text-danger',
    ];

    protected $fillable = [];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['total' => 'integer', 'subtotal' => 'integer', 'fee' => 'integer', 'fee_percent' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'email_sent_at' => 'datetime', 'terms_accepted_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PageSubmission::class, 'submission_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class, 'order_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'order_id')->orderBy('sequence');
    }

    /** Yang masuk saldo penyelenggara: dibayar pembeli dikurangi biaya layanan bizbos. */
    public function netAmount(): int
    {
        return $this->total - $this->fee;
    }

    public function buyerPaysFee(): bool
    {
        return $this->fee_payer === TicketSetting::PAYER_BUYER;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function url(): string
    {
        return route('eticket.order', [$this->page->slug, $this->access_token]);
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
