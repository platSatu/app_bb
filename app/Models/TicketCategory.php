<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    use HasActiveStatus, HasUuids;

    // sold & reserved hanya diubah oleh TicketOrderService (dengan lock).
    protected $fillable = ['name', 'description', 'price', 'quota', 'max_per_order', 'sale_starts_at', 'sale_ends_at', 'status'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quota' => 'integer',
            'sold' => 'integer',
            'reserved' => 'integer',
            'max_per_order' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class, 'category_id');
    }

    public function remaining(): int
    {
        return max(0, $this->quota - $this->sold - $this->reserved);
    }

    public function revenue(): int
    {
        return $this->sold * $this->price;
    }

    /** null = bisa dibeli; selain itu alasan yang ramah untuk pembeli. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            ! $this->isActive() => 'Tidak dijual',
            $this->sale_starts_at?->isFuture() === true => 'Dibuka '.$this->sale_starts_at->translatedFormat('d M Y H:i'),
            $this->sale_ends_at?->isPast() === true => 'Penjualan ditutup',
            $this->remaining() === 0 => 'Habis',
            default => null,
        };
    }

    public function maxBuyable(): int
    {
        return $this->unavailableReason() ? 0 : min($this->max_per_order, $this->remaining());
    }
}
