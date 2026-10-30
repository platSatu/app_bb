<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Models\InvoiceRegisterLink;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Owner: buat / ganti / buka-tutup link pendaftaran customer Invoice untuk branch miliknya (paket Invoice aktif). */
class RegisterLinkController extends Controller
{
    /** Belum ada: dibuat. Sudah ada: diganti token baru (link lama langsung tidak berlaku). */
    public function store(Request $request, string $branch): RedirectResponse
    {
        $this->authorizeBranch($request, $branch);

        $link = InvoiceRegisterLink::where('branch_id', $branch)->first() ?? new InvoiceRegisterLink();
        $renew = $link->exists;
        $link->forceFill(['branch_id' => $branch, 'token' => InvoiceRegisterLink::newToken(), 'is_active' => true])->save();

        return back()->with('success', $renew ? 'Link pendaftaran diganti. Link lama sudah tidak bisa dipakai.' : 'Link pendaftaran customer siap dibagikan.');
    }

    public function toggle(Request $request, string $branch): RedirectResponse
    {
        $this->authorizeBranch($request, $branch);

        $link = InvoiceRegisterLink::where('branch_id', $branch)->firstOrFail();
        $link->forceFill(['is_active' => ! $link->is_active])->save();

        return back()->with('success', $link->is_active ? 'Link pendaftaran dibuka lagi.' : 'Link pendaftaran ditutup sementara.');
    }

    private function authorizeBranch(Request $request, string $branch): void
    {
        abort_unless(in_array($branch, app(AppAccess::class)->ownerBranchIds($request->user(), 'invoices'), true), 404);
    }
}
