<?php

namespace App\Http\Controllers\Superadmin\Deposit;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function index(Request $request): View
    {
        $deposits = Deposit::query()
            ->with('user:id,name,email')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->tap(fn ($q) => $this->applyCommonFilters($q, $request, 'reference_number'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.deposit.index', compact('deposits'));
    }

    public function history(Request $request): View
    {
        $transactions = WalletTransaction::query()
            ->with('user:id,name,email')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->tap(fn ($q) => $this->applyCommonFilters($q, $request, 'description'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.deposit.history', compact('transactions'));
    }

    /** Filter yang sama untuk tab Deposit & History: cari user/kode + rentang tanggal. */
    private function applyCommonFilters(Builder $query, Request $request, string $ownColumn): void
    {
        $search = trim((string) $request->query('search'));

        $query
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where($ownColumn, 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));
    }
}
