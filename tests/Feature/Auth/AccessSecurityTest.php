<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

it('halaman yang butuh login menolak tamu dan mengarahkan ke login', function () {
    foreach (['dashboard', 'profile.edit', 'profile.withdrawals', 'whatsapp.devices.index', 'settings.company.show', 'superadmin.deposits.index'] as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }
});

it('user yang belum verifikasi email hanya bisa membuka halaman verifikasi', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);
    foreach (['dashboard', 'profile.edit', 'profile.withdrawals', 'whatsapp.devices.index', 'settings.company.show'] as $route) {
        $this->get(route($route))->assertRedirect(route('verification.notice'));
    }
    $this->getJson(route('whatsapp.notifications'))->assertStatus(409);
    $this->get(route('verification.notice'))->assertOk();

    $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, QueuedVerifyEmail::class);

    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($link)->assertRedirect();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

it('halaman login & halaman setelah login tidak disimpan browser, jadi Back setelah logout tidak menampilkan halaman lama', function () {
    $this->get(route('login'))->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

    $this->post(route('logout'))->assertRedirect('/');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('reset password mengeluarkan semua perangkat yang masih login', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->unverified()->create();
    DB::table('sessions')->insert(['id' => 'sesi-lain', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $rememberToken = $user->remember_token;

    $this->post(route('password.store'), [
        'token' => Password::createToken($user), 'email' => $user->email,
        'password' => 'PasswordBaru123', 'password_confirmation' => 'PasswordBaru123',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect($user->remember_token)->not->toBe($rememberToken)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('halaman superadmin tertutup untuk user biasa', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('superadmin.deposits.index'))->assertForbidden();
});
