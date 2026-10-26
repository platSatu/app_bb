<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Semua halaman yang butuh login hanya terbuka setelah email terverifikasi,
 * jadi tidak ada route yang lolos karena lupa memasang middleware 'verified'.
 * Yang dikecualikan hanya halaman untuk menyelesaikan verifikasi itu sendiri.
 */
class RequireVerifiedEmail
{
    public const MESSAGE = 'Verifikasi email Anda dulu ya. Klik link yang kami kirim ke email Anda.';

    private const ALLOWED = ['verification.*', 'logout', 'password.confirm', 'password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail() && $this->needsLogin($request) && ! $request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson()
                ? response()->json(['message' => self::MESSAGE], 409)
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }

    private function needsLogin(Request $request): bool
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        return collect($middleware)->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));
    }
}
