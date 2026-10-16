<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Menu;

/**
 * Staff hanya boleh mengelola akun pelanggan (tipe user).
 * Akun superadmin/staff hanya bisa diubah superadmin, supaya staff
 * tidak bisa menaikkan hak aksesnya sendiri.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->canAccess('superadmin.users');
    }

    public function create(User $actor): bool
    {
        return $actor->canAccess('superadmin.users', Menu::ABILITY_MANAGE);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->create($actor) && ($actor->isSuperadmin() || $target->user_type === User::TYPE_USER);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->update($actor, $target) && ! $actor->is($target);
    }
}
