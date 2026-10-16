<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu periode tagihan, mis. "Iuran Oktober". Isinya satu invoice per customer. */
class InvoiceBatch extends Model
{
    use HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['name', 'amount', 'due_date', 'use_late_fee'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'due_date' => 'date', 'use_late_fee' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class, 'category_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'batch_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
