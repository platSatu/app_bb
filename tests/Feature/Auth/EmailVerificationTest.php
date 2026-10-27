<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('halaman verifikasi email bisa dibuka', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->assertOk();
});

it('email terverifikasi lewat link yang benar', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($link)->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('link dengan hash salah tidak memverifikasi email', function () {
    $user = User::factory()->unverified()->create();

    $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1('email-lain')]);

    $this->actingAs($user)->get($link);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
