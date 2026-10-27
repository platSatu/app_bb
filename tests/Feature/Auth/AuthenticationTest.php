<?php

use App\Models\User;

// Alamat login app.bizbos ada di /auth/login, jadi test memakai nama route, bukan URL bawaan Breeze.
beforeEach(fn () => config(['services.turnstile.secret_key' => null]));

it('halaman login bisa dibuka', function () {
    $this->get(route('login'))->assertOk();
});

it('user bisa login lalu masuk ke dashboard', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('password salah tidak bisa login', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('user bisa logout', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});
