<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat unggah produk ke toko marketplace. Sumbernya produk Catalog Online sendiri
 * (catalog_product) atau produk dari marketplace lain (marketplace_product, mis. Lazada -> TikTok).
 */
class MarketplaceListing extends Model
{
    use HasUuids;

    public const SOURCE_CATALOG = 'catalog_product';
    public const SOURCE_MARKETPLACE = 'marketplace_product';

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_SUBMITTED => 'Terkirim (menunggu QC)',
        self::STATUS_FAILED => 'Gagal',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'response' => 'array', 'published_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(MarketplaceStore::class, 'store_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadge(): string
    {
        return $this->isSubmitted() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
    }
}
