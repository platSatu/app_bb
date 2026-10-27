<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('password bisa diganti dari halaman profil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('profile.edit'))
        ->put(route('password.update'), ['current_password' => 'password', 'password' => 'Rahasia#2026', 'password_confirmation' => 'Rahasia#2026'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('Rahasia#2026', $user->fresh()->password))->toBeTrue();
});

it('password lama yang salah ditolak dan password tidak berubah', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('profile.edit'))
        ->put(route('password.update'), ['current_password' => 'salah', 'password' => 'Rahasia#2026', 'password_confirmation' => 'Rahasia#2026'])
        ->assertSessionHasErrorsIn('updatePassword', 'current_password')
        ->assertRedirect(route('profile.edit'));

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
