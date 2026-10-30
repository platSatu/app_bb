<?php

namespace App\Http\Middleware;

use App\Models\WaApiKey;
use App\Services\Subscription\AppAccess;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * API WhatsApp, format sama dengan teleios: header X-WA-Token + X-WA-Secret.
 * Pengaman: secret dicocokkan dengan hash (constant-time), salah kredensial berulang dari satu IP diblokir sementara,
 * 20 request/menit per key, branch & paket WhatsApp harus aktif, setiap request tercatat.
 */
class VerifyWaApiKey
{
    private const PER_MINUTE = 20;

    private const MAX_FAILED_PER_MINUTE = 10;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $failed = 'wa-api-failed:'.$request->ip();
        if (RateLimiter::tooManyAttempts($failed, self::MAX_FAILED_PER_MINUTE)) {
            return self::error('Terlalu banyak percobaan Token/Secret Key yang salah. Coba lagi dalam '.RateLimiter::availableIn($failed).' detik.', 429);
        }

        $token = (string) $request->header('X-WA-Token');
        $secret = (string) $request->header('X-WA-Secret');
        $client = $token !== '' && $secret !== '' ? WaApiKey::with('device.branch')->where('api_key', $token)->first() : null;

        if (! $client?->secretMatches($secret)) {
            RateLimiter::hit($failed, 60);

            return self::error('Token atau Secret Key tidak valid.', 401);
        }
        if (! $client->is_active || $client->device?->branch?->status !== 'active') {
            return self::error('Akses API sedang dinonaktifkan oleh pemilik akun.', 403);
        }
        if (! app(AppAccess::class)->branchActive($client->device?->branch_id, 'whatsapp')) {
            return self::error('Paket WhatsApp untuk akun ini belum aktif atau sudah habis.', 403);
        }

        $limiter = 'wa-api:'.$client->getKey();
        if (RateLimiter::tooManyAttempts($limiter, self::PER_MINUTE)) {
            return self::error('Terlalu banyak request. Coba lagi dalam '.RateLimiter::availableIn($limiter).' detik.', 429);
        }
        RateLimiter::hit($limiter, 60);

        $request->attributes->set('waApiKey', $client);
        $response = $next($request);

        // ID antrean (untuk menampilkan tujuan & isi pesan di Request Terakhir) atau alasan bila ditolak.
        $body = $response instanceof JsonResponse ? $response->getData(true) : [];
        $outboxId = $response->isSuccessful() ? ($body['message']['id'] ?? null) : null;
        $client->logs()->create([
            'method' => $request->method(),
            'path' => substr($request->path(), 0, 100),
            'status' => $response->getStatusCode(),
            'outbox_id' => is_string($outboxId) && Str::isUuid($outboxId) ? $outboxId : null,
            'error' => $response->isSuccessful() ? null : (Str::limit((string) ($body['error'] ?? $body['message'] ?? ''), 250, '') ?: null),
            'ip' => $request->ip(),
        ]);
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $response;
    }

    /** Bentuk error sama dengan teleios ("error"), plus "success"/"message" seperti API bizbos lainnya. */
    public static function error(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'error' => $message, 'message' => $message], $status);
    }
}
