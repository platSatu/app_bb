<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasUuids;

    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_COMPANY = 'company';

    protected $fillable = ['name', 'description'];

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_PLATFORM)->whereNull('company_id');
    }
}
