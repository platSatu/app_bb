<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Support\UserAgent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mencatat halaman yang dibuka & aksi yang dilakukan pengguna yang login (UserActivityLog).
 * Ditulis di terminate(), setelah respons terkirim, jadi tidak memperlambat halaman.
 * Yang dicatat hanya route, path (tanpa query string), status, IP, dan perangkat; isi form tidak pernah disimpan.
 * Permintaan latar belakang (polling JSON/AJAX GET seperti inbox realtime) tidak dicatat.
 */
class LogUserActivity
{
    /** Route yang sering dipanggil otomatis oleh halaman, bukan oleh klik pengguna. */
    private const SKIP = ['*.realtime', '*.status', '*.progress', '*.health', '*.avatar', '*.media', 'whatsapp.notifications', '*.presence', '*.read'];

    public function handle(Request $request, Closure $next): Response
    {
        // Disimpan sebelum diproses supaya "Keluar" (user sudah logout setelahnya) tetap tercatat.
        $request->attributes->set('activity_user_id', $request->user()?->getKey());

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $userId = $request->user()?->getKey() ?? $request->attributes->get('activity_user_id');
        $route = $request->route()?->getName();

        if (! $userId || ($route && Str::is(self::SKIP, $route))
            || ($request->isMethod('GET') && ($request->expectsJson() || $request->ajax()))) {
            return;
        }

        try {
            UserActivityLog::create([
                'user_id' => $userId,
                'route_name' => $route,
                'method' => $request->method(),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                'status' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                ...collect(UserAgent::parse($request->userAgent()))->only(['browser', 'os', 'device_type'])->all(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e); // Gagal mencatat tidak boleh mengganggu pengguna.
        }
    }
}
