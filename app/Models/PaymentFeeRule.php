<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biaya pembayaran khusus satu pelanggan bizbos (per metode, atau semua metode), menimpa default di
 * Payment Gateway. Diatur di Superadmin › Deposit › Payment Gateway.
 */
class PaymentFeeRule extends Model
{
    use HasUuids;

    public const TYPE_FLAT = 'flat';
    public const TYPE_PERCENT = 'percent';

    public const TYPE_LABELS = [self::TYPE_FLAT => 'Flat (Rp)', self::TYPE_PERCENT => 'Persentase (%)'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['fee_value' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Flat = rupiah bulat; persen = dari nominal, dibulatkan ke atas. Tidak pernah negatif atau melebihi nominal. */
    public function feeFor(int $paidAmount): int
    {
        $fee = $this->fee_type === self::TYPE_PERCENT
            ? (int) ceil(round($paidAmount * $this->fee_value / 100, 4))
            : (int) round($this->fee_value);

        return max(0, min($fee, max(0, $paidAmount)));
    }

    public function valueLabel(): string
    {
        return $this->fee_type === self::TYPE_PERCENT
            ? rtrim(rtrim(number_format($this->fee_value, 2, ',', '.'), '0'), ',').'%'
            : 'Rp '.number_format((int) round($this->fee_value), 0, ',', '.');
    }
}
