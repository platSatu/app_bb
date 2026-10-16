<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cek captcha Cloudflare Turnstile ke server Cloudflare (siteverify).
 * Dilewati kalau TURNSTILE_SECRET_KEY belum diisi.
 */
class Turnstile implements ValidationRule
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // Tetap dijalankan walau field-nya kosong/tidak dikirim.
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = config('services.turnstile.secret_key');

        // Belum dikonfigurasi (mis. clone lokal) -> dilewati supaya form
        // tidak terkunci. Di production dicatat supaya tidak lolos diam-diam.
        if (! $secretKey) {
            if (app()->isProduction()) {
                Log::warning('[TURNSTILE] TURNSTILE_SECRET_KEY belum diisi, captcha tidak dicek.');
            }

            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Silakan selesaikan verifikasi captcha terlebih dahulu.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, [
                'secret' => $secretKey,
                'response' => $value,
                // IP asli pengunjung kalau lewat proxy Cloudflare.
                'remoteip' => request()->header('CF-Connecting-IP') ?: request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $fail('Verifikasi captcha sedang bermasalah. Silakan coba lagi.');

            return;
        }

        if (! $response->json('success')) {
            $fail('Verifikasi captcha gagal. Silakan coba lagi.');
        }
    }
}
