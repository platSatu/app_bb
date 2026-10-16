<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceCategory extends Model
{
    use HasActiveStatus, HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';

    public const LATE_FEE_MODES = [
        'none' => 'Tanpa denda',
        'flat' => 'Nominal tetap (sekali)',
        'percent' => 'Persen dari tagihan, per hari / per bulan',
        'percent_flat' => 'Persen per hari sampai hari ke-N, lalu nominal tetap per hari',
    ];

    protected $fillable = ['name', 'description', 'prefix', 'late_fee_mode', 'late_fee_flat', 'late_fee_percent', 'late_fee_period', 'late_fee_percent_days', 'email_enabled', 'reminder_days', 'status'];

    protected function casts(): array
    {
        return [
            'late_fee_flat' => 'integer',
            'late_fee_percent' => 'float',
            'late_fee_percent_days' => 'integer',
            'email_enabled' => 'boolean',
            'reminder_days' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(InvoiceSubscription::class, 'category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InvoiceBatch::class, 'category_id');
    }

    public function hasLateFee(): bool
    {
        return $this->late_fee_mode !== 'none';
    }

    /** Denda untuk tagihan $amount yang terlambat $days hari. */
    public function lateFeeFor(int $amount, int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $percent = $amount * $this->late_fee_percent / 100;

        return (int) round(match ($this->late_fee_mode) {
            'flat' => $this->late_fee_flat,
            'percent' => $percent * ($this->late_fee_period === 'month' ? (int) ceil($days / 30) : $days),
            'percent_flat' => $percent * min($days, (int) $this->late_fee_percent_days) + $this->late_fee_flat * max(0, $days - (int) $this->late_fee_percent_days),
            default => 0,
        });
    }

    public function lateFeeSummary(): string
    {
        $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
        $pct = rtrim(rtrim(number_format($this->late_fee_percent, 2, ',', '.'), '0'), ',').'%';

        return match ($this->late_fee_mode) {
            'flat' => $rp($this->late_fee_flat).' sekali',
            'percent' => $pct.' per '.($this->late_fee_period === 'month' ? 'bulan' : 'hari'),
            'percent_flat' => "{$pct}/hari s.d. hari ke-{$this->late_fee_percent_days}, lalu ".$rp($this->late_fee_flat).'/hari',
            default => 'Tanpa denda',
        };
    }
}
