<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentCallback extends Model
{
    use HasUuids;

    protected $fillable = ['provider', 'merchant_order_id', 'payload', 'signature_valid', 'result', 'message', 'ip_address'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'signature_valid' => 'boolean'];
    }
}
