<?php

namespace App\Models;

use App\Support\Apps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    use HasUuids;

    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_VOUCHER = 'voucher';
    public const SOURCE_TRIAL = 'trial';
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LABELS = [
        self::SOURCE_PURCHASE => 'Beli paket',
        self::SOURCE_VOUCHER => 'Pakai voucher',
        self::SOURCE_TRIAL => 'Trial',
        self::SOURCE_MANUAL => 'Diatur admin',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['days' => 'integer', 'previous_ends_at' => 'datetime', 'new_ends_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function appLabel(): string
    {
        return Apps::label($this->app_key);
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
