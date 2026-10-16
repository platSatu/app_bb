<?php

namespace App\Http\Controllers\Superadmin\Withdrawal;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function __construct(private readonly WithdrawalService $service)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $withdrawals = WalletWithdrawal::query()
            ->with(['user:id,name,email', 'approver:id,name'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->orderByRaw("CASE status WHEN 'needs_review' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.withdrawal.index', compact('withdrawals'));
    }

    public function approve(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        try {
            $withdrawal = $this->service->approve($withdrawal, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return match ($withdrawal->status) {
            WalletWithdrawal::STATUS_SUCCESS => back()->with('success', "Dana {$withdrawal->reference} berhasil dikirim ke {$withdrawal->bank_name} ••••{$withdrawal->account_last4}."),
            WalletWithdrawal::STATUS_NEEDS_REVIEW => back()->with('error', "Transfer {$withdrawal->reference} sudah dikirim tapi hasilnya belum pasti. Saldo user TIDAK dikembalikan otomatis. Cek status lalu selesaikan manual. {$withdrawal->failure_reason}"),
            default => back()->with('error', "Penarikan {$withdrawal->reference} gagal dan saldo user sudah dikembalikan. {$withdrawal->failure_reason}"),
        };
    }

    public function reject(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan'])['reason'];

        try {
            $this->service->reject($withdrawal, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Penarikan {$withdrawal->reference} ditolak dan saldo user sudah dikembalikan.");
    }

    public function check(WalletWithdrawal $withdrawal): RedirectResponse
    {
        return back()->with('info', "{$withdrawal->reference}: ".$this->service->checkStatus($withdrawal));
    }

    public function resolve(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'sent' => ['required', 'boolean'],
            'note' => ['required', 'string', 'max:300'],
        ], [], ['note' => 'catatan']);

        try {
            $this->service->resolve($withdrawal, $request->user(), (bool) $data['sent'], $data['note']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['sent']
            ? "{$withdrawal->reference} ditandai berhasil terkirim."
            : "{$withdrawal->reference} ditandai tidak terkirim dan saldo user sudah dikembalikan.");
    }
}
