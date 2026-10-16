<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Aturan bonus referral (1 baris): cair saat top up pertama yang berhasil dari orang yang diajak. */
class ReferralSetting extends Model
{
    use HasUuids;

    protected $fillable = ['is_active', 'reward_amount', 'min_deposit_amount'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'reward_amount' => 'integer', 'min_deposit_amount' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['is_active' => false, 'reward_amount' => 0, 'min_deposit_amount' => 0]);
    }

    public function isPayable(): bool
    {
        return $this->is_active && $this->reward_amount > 0;
    }
}
