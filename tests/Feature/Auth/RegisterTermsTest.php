<?php

use App\Models\User;
use App\Models\WebTermCondition;

beforeEach(fn () => config(['services.turnstile.secret_key' => null]));

it('daftar wajib centang Syarat & Ketentuan, dan waktu persetujuannya tersimpan', function () {
    $form = ['name' => 'Budi', 'email' => 'budi@mail.test', 'handphone' => '81234567890', 'password' => 'Rahasia#2026', 'password_confirmation' => 'Rahasia#2026'];

    $this->post(route('register'), $form)->assertSessionHasErrors('terms');
    expect(User::where('email', 'budi@mail.test')->exists())->toBeFalse();

    $terms = WebTermCondition::create(['name' => 'Syarat dan Ketentuan', 'descriptions' => '## 1. Umum', 'status' => 'active']);
    $this->post(route('register'), [...$form, 'terms' => '1'])->assertSessionHasNoErrors();
    $user = User::where('email', 'budi@mail.test')->first();
    expect($user->terms_accepted_at)->not->toBeNull()->and($user->terms_id)->toBe($terms->id);
});

it('halaman daftar menampilkan checkbox dan isi Syarat & Ketentuan dari database di popup', function () {
    WebTermCondition::create(['name' => 'Syarat dan Ketentuan', 'descriptions' => "## 1. Akun Anda\n\nIsi ketentuan.", 'status' => 'active']);

    $this->get(route('register'))->assertOk()
        ->assertSee('name="terms"', false)
        ->assertSee('id="termsModal"', false)
        ->assertSee('<h2>1. Akun Anda</h2>', false);
});
