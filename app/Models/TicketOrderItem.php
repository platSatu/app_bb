<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'order_id');
    }
}
