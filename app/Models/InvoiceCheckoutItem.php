<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu invoice di dalam pembayaran gabungan; nominal & denda dibekukan saat checkout dibuat. */
class InvoiceCheckoutItem extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'late_fee' => 'integer', 'total' => 'integer'];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(InvoiceCheckout::class, 'checkout_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
