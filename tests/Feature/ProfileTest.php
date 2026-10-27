<?php

use App\Models\User;

it('halaman profil bisa dibuka', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

it('nama dan nomor WhatsApp bisa diubah, email tidak ikut berubah', function () {
    $user = User::factory()->create();
    $email = $user->email;

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'handphone' => '081234567890', 'email' => 'lain@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->name)->toBe('Test User')
        ->and($user->handphone)->toBe('6281234567890')
        ->and($user->email)->toBe($email)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('nomor WhatsApp wajib diisi dan tidak boleh dipakai akun lain', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Test User'])->assertSessionHasErrors('handphone');
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Test User', 'handphone' => $other->handphone])->assertSessionHasErrors('handphone');
});

it('user tidak bisa menghapus akunnya sendiri (hanya superadmin yang bisa)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.edit'), ['password' => 'password'])->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
