<?php

namespace App\Http\Controllers\Invoice\Concerns;

use App\Models\UserBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Halaman Invoice dipakai owner (/invoices) & superadmin (/superadmin/invoices, hanya lihat). */
trait InInvoiceArea
{
    protected function isAdmin(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'superadmin.');
    }

    protected function routes(Request $request): string
    {
        return $this->isAdmin($request) ? 'superadmin.invoices' : 'invoices';
    }

    protected function shared(Request $request, string $tab): array
    {
        return ['tab' => $tab, 'routes' => $this->routes($request), 'isAdmin' => $this->isAdmin($request), 'canManage' => ! $this->isAdmin($request)];
    }

    protected function ownerBranches(Request $request): Collection
    {
        return UserBranch::where('user_id', $request->user()->getKey())->orderBy('name')->get(['id', 'name', 'status', 'company_id']);
    }

    protected function branchRequired(): RedirectResponse
    {
        return redirect()->route('settings.branches.index')
            ->with('info', 'Buat branch dulu ya, setiap invoice tercatat di salah satu branch Anda.');
    }
}
