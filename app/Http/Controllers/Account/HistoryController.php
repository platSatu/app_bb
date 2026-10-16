<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Semua mutasi saldo milik user yang login: top up, penjualan, tarik saldo, bonus, koreksi. */
class HistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $query = $request->user()->walletTransactions()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', "%{$search}%"))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));

        return view('profile.history', [
            'totalIn' => (int) (clone $query)->where('direction', WalletTransaction::CREDIT)->sum('amount'),
            'totalOut' => (int) (clone $query)->where('direction', WalletTransaction::DEBIT)->sum('amount'),
            'transactions' => $query->latest()->paginate(20)->withQueryString(),
        ]);
    }
}
