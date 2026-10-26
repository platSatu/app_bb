<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua halaman web.
 * - Dashboard tidak bisa dimuat di iframe situs lain (cegah clickjacking, mis. tombol setujui tarik saldo).
 *   Halaman publik /page/* tetap boleh di-embed owner di website mereka.
 * - Browser tidak menebak tipe file, referrer tidak membocorkan URL lengkap (token) ke situs lain.
 * - Kamera hanya untuk situs ini sendiri (scanner tiket/absensi).
 * - Halaman yang dibuka saat login (dan halaman login/daftar) tidak disimpan browser:
 *   setelah logout, tombol Back meminta ulang ke server lalu diarahkan ke login, bukan menampilkan salinan lama.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        if (! $request->is('page/*') && ! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()', false);

        if ($this->isPrivate($request, $response)) {
            $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $headers->set('Pragma', 'no-cache');
            $headers->set('Expires', '0');
        }

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }

    private function isPrivate(Request $request, Response $response): bool
    {
        // File/gambar yang sengaja diberi cache (public / max-age) dibiarkan.
        $cache = (string) $response->headers->get('Cache-Control');
        if (str_contains($cache, 'public') || (str_contains($cache, 'max-age=') && ! str_contains($cache, 'max-age=0'))) {
            return false;
        }

        return $request->user() !== null || $request->routeIs('login', 'register', 'password.*', 'verification.*', 'logout');
    }
}
