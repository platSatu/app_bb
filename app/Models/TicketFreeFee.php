<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Catatan biaya tiket gratis per event. Hanya diubah lewat FreeTicketFeeService. */
class TicketFreeFee extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'free_allowance' => 'integer', 'paid_units' => 'integer', 'version' => 'integer', 'exempt' => 'boolean', 'closed_at' => 'datetime'];
    }
}
