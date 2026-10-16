<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogProduct extends Model
{
    use HasActiveStatus, HasUuids;

    public const TYPE_PHYSICAL = 'physical';
    public const TYPE_DIGITAL = 'digital';

    public const MAX_IMAGES = 8;

    // page_id, slug & type diisi sistem.
    protected $fillable = ['category_id', 'name', 'description', 'price', 'sale_price', 'stock', 'status'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'sale_price' => 'integer', 'stock' => 'integer', 'sort_order' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CatalogProductImage::class, 'product_id')->orderBy('sort_order');
    }

    /** Harga yang dibayar: harga coret dipakai kalau diisi dan lebih murah. */
    public function finalPrice(): int
    {
        return $this->sale_price !== null && $this->sale_price < $this->price ? $this->sale_price : $this->price;
    }

    public function isDiscounted(): bool
    {
        return $this->finalPrice() < $this->price;
    }

    public function tracksStock(): bool
    {
        return $this->stock !== null;
    }

    public function isSoldOut(): bool
    {
        return $this->tracksStock() && $this->stock <= 0;
    }

    public function cover(): ?CatalogProductImage
    {
        return $this->images->firstWhere('status', CatalogProductImage::STATUS_ACTIVE);
    }

    public function features(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->description))));
    }
}
