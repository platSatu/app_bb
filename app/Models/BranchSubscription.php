<?php

namespace App\Models;

use App\Support\Apps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Masa aktif satu layanan di satu branch. Aktif = ends_at belum lewat. */
class BranchSubscription extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'limits' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }

    public function appLabel(): string
    {
        return Apps::label($this->app_key);
    }

    /** Sisa hari (dibulatkan ke atas); 0 kalau sudah habis. */
    public function daysLeft(): int
    {
        return $this->isActive() ? (int) ceil(now()->diffInHours($this->ends_at) / 24) : 0;
    }
}
