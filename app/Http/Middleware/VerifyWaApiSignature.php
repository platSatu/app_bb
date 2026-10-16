<?php

namespace App\Http\Middleware;

use App\Models\WaApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * API WhatsApp: X-Bizbos-Key + X-Bizbos-Timestamp + X-Bizbos-Signature (skema sama dengan API Invoice).
 * Signature = hex HMAC-SHA256(secret, timestamp + "\n" + METHOD + "\n" + path + "\n" + body).
 * Dibatasi 20 pesan/menit per key supaya nomor tidak dianggap spam oleh WhatsApp.
 */
class VerifyWaApiSignature
{
    private const TOLERANCE_SECONDS = 300;
    private const PER_MINUTE = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $key = (string) $request->header('X-Bizbos-Key');
        $timestamp = (string) $request->header('X-Bizbos-Timestamp');
        $signature = strtolower((string) $request->header('X-Bizbos-Signature'));

        if ($key === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS || $signature === '') {
            return $this->deny('Header X-Bizbos-Key, X-Bizbos-Timestamp (maks selisih 5 menit), dan X-Bizbos-Signature wajib diisi.', 401);
        }

        $client = WaApiKey::with('device.branch')->where('api_key', $key)->first();
        $payload = $timestamp."\n".strtoupper($request->method())."\n".$request->getPathInfo()."\n".$request->getContent();

        if (! $client || ! hash_equals(hash_hmac('sha256', $payload, (string) $client->secret), $signature)) {
            return $this->deny('API key atau signature tidak cocok.', 401);
        }
        if (! $client->is_active || $client->device?->branch?->status !== 'active') {
            return $this->deny('Akses API sedang dinonaktifkan oleh pemilik akun.', 403);
        }

        $limiter = 'wa-api:'.$client->getKey();
        if (RateLimiter::tooManyAttempts($limiter, self::PER_MINUTE)) {
            return $this->deny('Terlalu banyak request. Coba lagi dalam '.RateLimiter::availableIn($limiter).' detik.', 429);
        }
        RateLimiter::hit($limiter, 60);

        $request->attributes->set('waApiKey', $client);
        $response = $next($request);

        $client->logs()->create(['method' => $request->method(), 'path' => substr($request->path(), 0, 100), 'status' => $response->getStatusCode(), 'ip' => $request->ip()]);
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $response;
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
