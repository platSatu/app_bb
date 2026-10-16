<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Top Up',
        self::STATUS_REWARDED => 'Bonus Cair',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['reward_amount' => 'integer', 'rewarded_at' => 'datetime'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
