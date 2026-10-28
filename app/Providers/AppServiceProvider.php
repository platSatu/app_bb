<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cache status paket per request/job; diulang dari nol di request/job berikutnya.
        $this->app->scoped(\App\Services\Subscription\AppAccess::class);
        // Satu instance per proses: menyimpan job antrean yang sedang dikirim (lihat EmailLogRecorder).
        $this->app->singleton(\App\Support\EmailLogRecorder::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));

        // Riwayat semua email (superadmin > Riwayat Email).
        Event::subscribe(\App\Support\EmailLogRecorder::class);

        // Email akun dalam Bahasa Indonesia.
        VerifyEmail::toMailUsing(fn ($user, string $url) => (new MailMessage())
            ->subject('Verifikasi email akun '.config('app.name'))
            ->greeting('Halo '.$user->name.',')
            ->line('Terima kasih sudah mendaftar. Klik tombol di bawah untuk memverifikasi email Anda.')
            ->action('Verifikasi Email', $url)
            ->line('Link berlaku '.config('auth.verification.expire', 60).' menit. Kalau Anda tidak merasa mendaftar, abaikan email ini.'));

        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage())
            ->subject('Reset password akun '.config('app.name'))
            ->greeting('Halo '.$user->name.',')
            ->line('Kami menerima permintaan reset password untuk akun Anda.')
            ->action('Buat Password Baru', route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]))
            ->line('Link berlaku '.config('auth.passwords.users.expire', 60).' menit. Kalau Anda tidak meminta reset password, abaikan email ini; password Anda tidak berubah.'));
    }
}
