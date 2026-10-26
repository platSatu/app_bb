<?php

namespace App\Http\Controllers\Superadmin\Deposit;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function index(Request $request): View
    {
        // Filter cari + tanggal dipakai bersama oleh tabel dan kotak ringkasan; filter status hanya untuk tabel.
        $filtered = Deposit::query()->tap(fn ($q) => $this->applyCommonFilters($q, $request, 'reference_number'));

        $deposits = (clone $filtered)
            ->with('user:id,name,email')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.deposit.index', ['deposits' => $deposits, 'summary' => $this->summary($request, $filtered)]);
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

    /**
     * Kotak ringkasan Data Deposit, mengikuti pencarian & rentang tanggal yang sama dengan tabel:
     * deposit berhasil, sisa saldo user per akhir rentang, dan penjualan paket.
     */
    private function summary(Request $request, Builder $deposits): array
    {
        $topUp = (clone $deposits)->where('status', Deposit::STATUS_SUCCESS)->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS trx, COUNT(DISTINCT user_id) AS users')->first();

        $sales = WalletTransaction::query()->where('type', WalletTransaction::TYPE_PURCHASE)
            ->tap(fn ($q) => $this->applyCommonFilters($q, $request, 'description'))->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) AS total, COUNT(*) AS trx')->first();

        // Saldo per "sampai tanggal" dihitung dari buku besar (kredit - debit sampai hari itu); tanpa tanggal = saldo saat ini.
        $until = $request->date('date_to')?->endOfDay();
        $perUser = ($until
            ? WalletTransaction::query()->where('created_at', '<=', $until)->groupBy('user_id')->selectRaw(
                "user_id, SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) - SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) AS balance")
            : Wallet::query()->select('user_id', 'balance')
        )->tap(fn ($q) => $this->searchUsers($q, $request));

        $balance = DB::query()->fromSub($perUser, 'per_user')
            ->selectRaw('COALESCE(SUM(balance), 0) AS total, SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) AS users')->first();

        return [
            'from' => $request->date('date_from'),
            'until' => $until,
            'top_up' => ['total' => (int) $topUp->total, 'trx' => (int) $topUp->trx, 'users' => (int) $topUp->users],
            'balance' => ['total' => (int) $balance->total, 'users' => (int) $balance->users],
            'sales' => ['total' => (int) $sales->total, 'trx' => (int) $sales->trx],
        ];
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

    /** Pencarian nama/email user saja (untuk saldo, yang tidak punya kolom kode/keterangan sendiri). */
    private function searchUsers(Builder $query, Request $request): void
    {
        $search = trim((string) $request->query('search'));

        $query->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
    }
}
