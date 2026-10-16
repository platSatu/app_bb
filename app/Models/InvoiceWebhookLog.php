<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceWebhookLog extends Model
{
    use HasUuids;

    protected $fillable = [];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isSuccessful(): bool
    {
        return $this->http_status !== null && $this->http_status >= 200 && $this->http_status < 300;
    }
}
