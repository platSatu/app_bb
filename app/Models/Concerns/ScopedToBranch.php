<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\UserBranch;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data milik satu branch. Owner hanya melihat branch miliknya; tim platform sesuai izin
 * menu superadmin di konstanta ADMIN_PERMISSION model (mis. superadmin.invoices).
 */
trait ScopedToBranch
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function scopeAccessibleBy(Builder $query, User $user, string $ability = Menu::ABILITY_VIEW): Builder
    {
        if (! $user->isPlatformTeam()) {
            return $query->whereIn($this->qualifyColumn('branch_id'), UserBranch::where('user_id', $user->getKey())->select('id'));
        }

        return $user->canAccess(static::ADMIN_PERMISSION, $ability) ? $query : $query->whereRaw('1 = 0');
    }
}
