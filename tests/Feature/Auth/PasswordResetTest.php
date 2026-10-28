<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['services.turnstile.secret_key' => null]);
    Notification::fake();
});

it('halaman lupa password bisa dibuka', function () {
    $this->get(route('password.request'))->assertOk();
});

it('link reset dikirim ke email yang terdaftar', function () {
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, QueuedResetPassword::class);
});

it('email yang tidak terdaftar mendapat pesan yang sama, supaya daftar email tidak bisa ditebak', function () {
    $this->post(route('password.email'), ['email' => 'tidak-ada@mail.test'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('halaman reset password dari link email bisa dibuka, lalu password baru tersimpan', function () {
    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) use ($user) {
        $this->get(route('password.reset', $notification->token))->assertOk();

        $this->post(route('password.store'), [
            'token' => $notification->token, 'email' => $user->email,
            'password' => 'Rahasia#2026', 'password_confirmation' => 'Rahasia#2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('Rahasia#2026', $user->fresh()->password))->toBeTrue();
});
