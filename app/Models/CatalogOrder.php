<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogOrder extends Model
{
    use HasUuids;

    public const STATUS_NEW = 'new';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Baru',
        self::STATUS_PAID => 'Lunas',
        self::STATUS_PROCESSING => 'Diproses / Dikirim',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const STATUS_BADGES = [
        self::STATUS_NEW => 'bg-info-subtle text-info',
        self::STATUS_PAID => 'bg-primary-subtle text-primary',
        self::STATUS_PROCESSING => 'bg-warning-subtle text-warning',
        self::STATUS_COMPLETED => 'bg-success-subtle text-success',
        self::STATUS_CANCELLED => 'bg-danger-subtle text-danger',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['total' => 'integer', 'stock_restored' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogOrderItem::class, 'order_id');
    }

    /** Selesai & Dibatalkan sudah final, tidak bisa diubah lagi (stok tidak bolak-balik). */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
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
