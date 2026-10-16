<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserBranch extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    // user_id, company_id & slug diisi sistem, tidak pernah dari form.
    protected $fillable = ['name', 'phone', 'handphone', 'address', 'city', 'description', 'status'];

    protected static function booted(): void
    {
        static::creating(function (UserBranch $branch) {
            $branch->slug ??= static::uniqueSlug($branch->company_id, $branch->name);
        });
    }

    public static function uniqueSlug(string $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;

        while (static::where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        return $query->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$keyword}%")
            ->orWhere('city', 'like', "%{$keyword}%")));
    }
}
