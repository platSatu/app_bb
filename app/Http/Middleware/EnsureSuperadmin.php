<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu area /superadmin: hanya superadmin dan staff.
 * Izin per menu dicek lagi oleh middleware 'permission'.
 */
class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isPlatformTeam(), 403);

        return $next($request);
    }
}
