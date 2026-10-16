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
        abort_unless($request->user()?->canAccess($key, $ability), 403);

        return $next($request);
    }
}
