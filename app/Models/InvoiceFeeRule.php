<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Biaya transaksi Invoice per customer (branch) & per metode. Lihat App\Services\Invoice\InvoiceFees. */
class InvoiceFeeRule extends Model
{
    use HasUuids;

    public const TYPE_FLAT = 'flat';
    public const TYPE_PERCENT = 'percent';

    public const BEARER_MERCHANT = 'merchant';
    public const BEARER_PAYER = 'payer';

    public const TYPE_LABELS = [self::TYPE_FLAT => 'Flat (Rp)', self::TYPE_PERCENT => 'Persentase (%)'];
    public const BEARER_LABELS = [self::BEARER_MERCHANT => 'Customer (dipotong dari saldo)', self::BEARER_PAYER => 'Pembayar (ditambahkan ke total)'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['fee_value' => 'float'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class);
    }

    public function valueLabel(): string
    {
        return $this->fee_type === self::TYPE_PERCENT
            ? rtrim(rtrim(number_format($this->fee_value, 2, ',', '.'), '0'), ',').'%'
            : 'Rp '.number_format((int) round($this->fee_value), 0, ',', '.');
    }
}
