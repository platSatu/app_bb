<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Simpan user baru/lama. Tipe & role hanya diproses kalau pelakunya superadmin.
     * User baru selalu langsung aktif.
     */
    public function save(User $user, array $data, User $actor): User
    {
        $isNew = ! $user->exists;
        $wasActive = $user->isActive();

        $this->guardSelf($user, $data, $actor);

        DB::transaction(function () use ($user, $data, $actor, $isNew) {
            $user->fill(Arr::only($data, ['name', 'email', 'handphone']));

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            if ($isNew) {
                $user->forceFill(['status' => User::STATUS_ACTIVE, 'email_verified_at' => now()]);
            } elseif (isset($data['status'])) {
                $user->status = $data['status'];
            }

            $user->user_type = $actor->isSuperadmin()
                ? ($data['user_type'] ?? User::TYPE_USER)
                : ($user->user_type ?? User::TYPE_USER);

            $user->save();

            if ($actor->isSuperadmin()) {
                $user->roles()->sync($user->isStaff() && filled($data['role_id'] ?? null) ? [$data['role_id']] : []);
            }
        });

        if (! $isNew && $wasActive && ! $user->isActive()) {
            $user->logoutEverywhere();
        }

        return $user;
    }

    private function guardSelf(User $user, array $data, User $actor): void
    {
        if (! $user->is($actor)) {
            return;
        }

        $demoted = ($data['status'] ?? User::STATUS_ACTIVE) !== User::STATUS_ACTIVE
            || ($actor->isSuperadmin() && ($data['user_type'] ?? User::TYPE_SUPERADMIN) !== User::TYPE_SUPERADMIN);

        if ($demoted) {
            throw ValidationException::withMessages([
                'status' => 'Akun Anda sendiri tidak bisa dinonaktifkan atau diturunkan tipenya.',
            ]);
        }
    }
}
