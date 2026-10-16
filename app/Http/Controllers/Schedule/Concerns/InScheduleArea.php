<?php

namespace App\Http\Controllers\Schedule\Concerns;

use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Halaman Schedules dipakai owner (/schedules) & superadmin (/superadmin/schedules). */
trait InScheduleArea
{
    protected function isAdmin(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'superadmin.');
    }

    protected function routes(Request $request): string
    {
        return $this->isAdmin($request) ? 'superadmin.schedules' : 'schedules';
    }

    protected function canManage(Request $request): bool
    {
        return ! $this->isAdmin($request) || $request->user()->canAccess('superadmin.schedules', Menu::ABILITY_MANAGE);
    }

    protected function branchRequired(): RedirectResponse
    {
        return redirect()->route('settings.branches.index')
            ->with('info', 'Buat branch dulu ya, semua data schedules tercatat di salah satu branch Anda.');
    }
}
