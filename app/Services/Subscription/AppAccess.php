<?php

namespace App\Services\Subscription;

use App\Models\BranchSubscription;
use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya jawaban untuk "layanan X di branch ini masih aktif?".
 * Dipakai menu, route, scope data, halaman publik, API, dan proses otomatis.
 * Hasil di-cache per request/job (didaftarkan scoped di AppServiceProvider).
 */
class AppAccess
{
    /** Izin superadmin di model (ADMIN_PERMISSION) -> layanan pemilik datanya. */
    private const PERMISSION_APPS = [
        'superadmin.schedules' => 'schedules',
        'superadmin.invoices' => 'invoices',
    ];

    private array $branches = [];

    private array $owners = [];

    public static function appForPermission(?string $permission): ?string
    {
        return self::PERMISSION_APPS[$permission] ?? null;
    }

    /** Subquery id branch yang layanan $app-nya masih aktif ('any' = layanan apa saja). */
    public static function activeBranches(string $app): Builder
    {
        return BranchSubscription::query()->select('branch_id')->when($app !== \App\Support\Apps::ANY, fn ($q) => $q->where('app_key', $app))->where('ends_at', '>', now());
    }

    public function branchActive(?string $branchId, string $app): bool
    {
        if (! $branchId) {
            return false;
        }

        return $this->branches[$branchId.'|'.$app] ??= BranchSubscription::where('branch_id', $branchId)
            ->where('app_key', $app)->where('ends_at', '>', now())->exists();
    }

    /** Branch milik owner yang layanan $app-nya aktif. */
    public function ownerBranchIds(User $user, string $app): array
    {
        return $this->owners[$user->getKey().'|'.$app] ??= UserBranch::where('user_id', $user->getKey())
            ->whereIn('id', self::activeBranches($app))->pluck('id')->all();
    }

    public function ownerHas(User $user, string $app): bool
    {
        return $this->ownerBranchIds($user, $app) !== [];
    }
}
