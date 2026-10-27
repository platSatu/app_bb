<?php

use App\Models\User;

it('halaman utama mengarahkan tamu ke login dan user ke dashboard', function () {
    $this->get('/')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});
