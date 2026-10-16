<?php

namespace App\Http\Controllers\Superadmin\Withdrawal;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\Withdrawal\BankAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BankAccountController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $accounts = BankAccount::query()
            ->with('user:id,name,email')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('account_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->orderByRaw("CASE status WHEN 'pending_review' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.withdrawal.bank-accounts', compact('accounts'));
    }

    public function review(Request $request, BankAccount $account, BankAccountService $service): RedirectResponse
    {
        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'required_if:approve,0', 'string', 'max:500'],
        ], ['note.required_if' => 'Tulis alasan penolakan supaya user tahu apa yang perlu diperbaiki.']);

        try {
            $service->review($account, $request->user(), (bool) $data['approve'], $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rekening '.$account->masked().($data['approve'] ? ' disetujui dan sekarang aktif.' : ' ditolak.'));
    }
}
