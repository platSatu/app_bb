<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun member (murid/orang tua & pengajar) hanya boleh membuka halaman login-only milik portal;
 * menu bisnis tertutup. Halaman publik (bayar tagihan, tiket, link daftar) tetap bisa dibuka.
 * Sebaliknya akun bisnis tidak masuk ke halaman portal.
 */
class SeparatePortalMember
{
    private const MEMBER_ROUTES = ['portal.*', 'logout', 'password.update', 'password.confirm', 'verification.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user || ! $route || $request->routeIs('portal.join', 'portal.join.store')) {
            return $next($request);
        }

        $needsLogin = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:')));

        if ($user->isMember() && $needsLogin && ! $request->routeIs(...self::MEMBER_ROUTES)) {
            abort_unless($request->isMethod('GET'), 403);

            return redirect()->route('portal.home');
        }

        if (! $user->isMember() && $request->routeIs('portal.*')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
