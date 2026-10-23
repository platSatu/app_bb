<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

// Di testing Laravel melewati cek CSRF; di sini dinyalakan supaya 419 benar-benar terjadi.
beforeEach(function () {
    $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
        protected function runningUnitTests() { return false; }
    });
});

it('sesi habis: form dikirim diarahkan ke login, bukan halaman 419', function () {
    $this->post(route('login'), ['email' => 'a@b.test', 'password' => 'x', '_token' => 'basi'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('warning')
        ->assertSessionHasInput('email', 'a@b.test');

    $this->get(route('login'))->assertSee('Sesi Anda sudah berakhir');
});

it('masih login tapi token basi: kembali ke halaman tadi dengan isian tetap', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('dashboard'))
        ->post(route('bills.pay'), ['branch_id' => 'x', '_token' => 'basi'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('warning')
        ->assertSessionHasInput('branch_id', 'x');
});

it('logout dengan token basi tetap keluar dan ke halaman login', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'), ['_token' => 'basi'])->assertRedirect(route('login'));
    $this->assertGuest();
});

it('permintaan AJAX dapat JSON 419 berisi alamat login', function () {
    $this->postJson(route('login'), ['_token' => 'basi'])->assertStatus(419)->assertJsonPath('redirect', route('login'));
});
