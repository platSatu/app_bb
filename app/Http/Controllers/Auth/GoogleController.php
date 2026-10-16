<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Masuk / daftar dengan Google. State OAuth disimpan di sesi (bukan stateless)
 * supaya callback palsu ditolak dan korban tidak bisa dilogin-kan ke akun penyerang.
 */
class GoogleController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('services.google.client_id'), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Masuk dengan Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        $email = Str::lower((string) $google->getEmail());
        if ($email === '' || ! ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false)) {
            return $this->fail('Email akun Google Anda belum terverifikasi oleh Google.');
        }

        $isNew = false;
        $user = DB::transaction(function () use ($google, $email, $request, &$isNew) {
            $user = User::where('google_id', $google->getId())->lockForUpdate()->first()
                ?? User::where('email', $email)->lockForUpdate()->first();

            if (! $user) {
                $isNew = true;
                $user = User::create([
                    'name' => Str::limit($google->getName() ?: Str::before($email, '@'), 255, ''),
                    'email' => $email,
                    'password' => Str::random(40),
                ]);
                $user->forceFill(['google_id' => $google->getId(), 'status' => User::STATUS_ACTIVE, 'email_verified_at' => now()])->save();
                $this->referrals->recordSignup($user, $request->session()->pull('referral_code'));

                return $user;
            }

            // Email sama tapi sudah tertaut ke akun Google lain: jangan ditimpa.
            if ($user->google_id && $user->google_id !== $google->getId()) {
                return null;
            }

            $fill = ['google_id' => $google->getId()];
            // Belum pernah verifikasi email: Google membuktikan pemiliknya, jadi password lama
            // (bisa saja dibuat orang lain yang mendaftar duluan) diganti dan sesi lama diputus.
            if (! $user->email_verified_at) {
                $fill += ['email_verified_at' => now(), 'password' => Str::random(40), 'remember_token' => Str::random(60)];
            }
            $user->forceFill($fill)->save();

            return $user;
        });

        if (! $user) {
            return $this->fail('Email ini sudah terhubung dengan akun Google lain. Silakan masuk dengan email dan password.');
        }

        if (! $user->isActive()) {
            return $this->fail(User::INACTIVE_MESSAGE);
        }

        if ($isNew) {
            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        if (! $user->handphone) {
            return redirect()->route('profile.edit')->with('warning', $isNew
                ? 'Akun Anda sudah dibuat. Lengkapi nomor WhatsApp dulu ya supaya notifikasi penting bisa kami kirim.'
                : 'Lengkapi nomor WhatsApp Anda dulu ya supaya notifikasi penting bisa kami kirim.');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
