<?php

namespace App\Http\Middleware;

use App\Support\Menu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $key, string $ability = Menu::ABILITY_VIEW): Response
    {
        $user = $request->user();

        if ($user?->canAccess($key, $ability)) {
            return $next($request);
        }

        // Menu aplikasi yang paketnya belum aktif/habis: arahkan ke Paket Saya, bukan halaman 403.
        $app = Menu::find($key)['app'] ?? null;

        if ($user && $app && ! $user->isPlatformTeam()) {
            $message = 'Layanan '.\App\Support\Apps::label($app).' belum aktif atau masa aktifnya sudah habis. Aktifkan paket dulu ya.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->route('profile.packages')->with('warning', $message);
        }

        abort(403);
    }
}
