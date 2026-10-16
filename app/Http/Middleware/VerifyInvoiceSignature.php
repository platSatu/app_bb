<?php

namespace App\Http\Middleware;

use App\Models\InvoiceApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Invoice: X-Bizbos-Key + X-Bizbos-Timestamp + X-Bizbos-Signature.
 * Signature = hex HMAC-SHA256(secret, timestamp + "\n" + METHOD + "\n" + path + "\n" + body),
 * path mulai dari /api/... (tanpa domain/folder). Timestamp maksimal selisih 5 menit.
 */
class VerifyInvoiceSignature
{
    private const TOLERANCE_SECONDS = 300;
    private const PER_MINUTE = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $key = (string) $request->header('X-Bizbos-Key');
        $timestamp = (string) $request->header('X-Bizbos-Timestamp');
        $signature = strtolower((string) $request->header('X-Bizbos-Signature'));

        if ($key === '' || $timestamp === '' || $signature === '') {
            return $this->deny('Header X-Bizbos-Key, X-Bizbos-Timestamp, dan X-Bizbos-Signature wajib diisi.', 401);
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return $this->deny('Timestamp tidak valid atau selisih lebih dari 5 menit. Pastikan jam server Anda benar.', 401);
        }

        $client = InvoiceApiClient::with('branch')->where('api_key', $key)->first();
        $payload = $timestamp."\n".strtoupper($request->method())."\n".$request->getPathInfo()."\n".$request->getContent();

        if (! $client || ! hash_equals(hash_hmac('sha256', $payload, (string) $client->secret), $signature)) {
            return $this->deny('API key atau signature tidak cocok.', 401);
        }

        if (! $client->is_active || $client->branch?->status !== 'active') {
            return $this->deny('Akses API sedang dinonaktifkan oleh pemilik akun.', 403);
        }

        $limiter = 'invoice-api:'.$client->getKey();

        if (RateLimiter::tooManyAttempts($limiter, self::PER_MINUTE)) {
            return $this->deny('Terlalu banyak request. Coba lagi dalam '.RateLimiter::availableIn($limiter).' detik.', 429);
        }

        RateLimiter::hit($limiter, 60);

        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('invoiceClient', $client);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
