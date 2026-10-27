<?php

use App\Models\User;

// Syarat & Ketentuan dicek terpisah di RegisterTermsTest.
beforeEach(fn () => config(['services.turnstile.secret_key' => null]));

it('halaman daftar bisa dibuka', function () {
    $this->get(route('register'))->assertOk();
});

it('user baru bisa daftar, nomor WhatsApp dirapikan, lalu langsung masuk ke dashboard', function () {
    $this->post(route('register'), [
        'name' => 'Test User', 'email' => 'test@example.com', 'handphone' => '081234567890',
        'password' => 'Rahasia#2026', 'password_confirmation' => 'Rahasia#2026', 'terms' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::where('email', 'test@example.com')->value('handphone'))->toBe('6281234567890');
});
