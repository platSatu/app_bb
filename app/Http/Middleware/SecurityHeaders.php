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

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $response;
    }
}
