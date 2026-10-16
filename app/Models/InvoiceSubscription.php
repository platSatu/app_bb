<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSubscription extends Model
{
    use HasActiveStatus, HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InvoiceCategory::class, 'category_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
