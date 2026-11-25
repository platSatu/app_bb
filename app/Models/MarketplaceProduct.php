<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Produk yang ditarik dari toko marketplace (snapshot terakhir saat sinkron). */
class MarketplaceProduct extends Model
{
    use HasUuids;

    // Diisi oleh proses sinkron, bukan dari form.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'special_price' => 'integer',
            'stock' => 'integer',
            'raw' => 'array',
            'external_updated_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function finalPrice(): int
    {
        return $this->special_price && $this->special_price < $this->price ? $this->special_price : $this->price;
    }

    public function isDiscounted(): bool
    {
        return $this->finalPrice() < $this->price;
    }

    public function statusBadge(): string
    {
        return match (strtolower((string) $this->status)) {
            'active', 'live' => 'bg-success-subtle text-success',
            'pending', 'pending qc' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
