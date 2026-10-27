<?php

use App\Models\User;

it('halaman konfirmasi password bisa dibuka', function () {
    $this->actingAs(User::factory()->create())->get(route('password.confirm'))->assertOk();
});

it('password yang benar dikonfirmasi', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('password yang salah ditolak', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm'), ['password' => 'salah'])
        ->assertSessionHasErrors('password');
});
