<?php

namespace App\Services\Role;

use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /** @param array<string, string> $permissions permission_key => ability */
    public function savePlatformRole(Role $role, array $attributes, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $attributes, $permissions) {
            $role->fill($attributes);
            $role->forceFill(['scope' => Role::SCOPE_PLATFORM, 'company_id' => null])->save();

            $role->permissions()->delete();
            $role->permissions()->createMany(
                collect($permissions)->map(fn (string $ability, string $key) => [
                    'permission_key' => $key,
                    'ability' => $ability,
                ])->values()->all()
            );

            return $role;
        });
    }
}
