<?php

namespace App\Http\Controllers\Superadmin\Deposit;

use App\Http\Controllers\Controller;
use App\Services\Deposit\MutationLedger;
use App\Services\Payment\DuitkuDisbursementClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/** Superadmin › Deposit › Mutasi: semua uang masuk (Kredit) & keluar (Debit) lewat Duitku bizbos. Hanya baca. */
class MutationController extends Controller
{
    /** Saldo Duitku disimpan sebentar: banyak tab admin terbuka tetap hanya 1 panggilan ke Duitku per 30 detik. */
    private const BALANCE_CACHE_SECONDS = 30;

    public function index(Request $request, MutationLedger $ledger): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'in:'.implode(',', array_keys(MutationLedger::SOURCES))],
            'direction' => ['nullable', 'in:credit,debit'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'source' => $validated['source'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'from' => $request->date('date_from')?->startOfDay(),
            'until' => $request->date('date_to')?->endOfDay(),
        ];

        return view('superadmin.deposit.mutations', [
            'rows' => $ledger->paginate($filters),
            'summary' => $ledger->summary($filters),
            'filters' => $filters,
        ]);
    }

    /**
     * Saldo asli Duitku Disbursement + saldo tercatat saat ini, untuk kartu yang diperbarui otomatis.
     * Hanya angka saldo; kredensial & respons mentah Duitku tidak pernah dikirim ke browser.
     */
    public function duitkuBalance(MutationLedger $ledger): JsonResponse
    {
        $duitku = $this->duitku();

        if (isset($duitku['error'])) {
            return response()->json(['ok' => false, 'message' => $duitku['error']], 502);
        }

        $recorded = $ledger->balanceUntil();

        return response()->json([
            'ok' => true,
            'sandbox' => $duitku['sandbox'],
            'balance' => $duitku['balance'],
            'effective' => $duitku['effective'],
            'recorded' => $recorded,
            'difference' => $duitku['balance'] - $recorded,
            'checked_at' => $duitku['checked_at'],
        ])->header('Cache-Control', 'no-store');
    }

    /** Tab Laba & Neraca: laba per aplikasi & per pelanggan untuk satu periode, neraca posisi saat ini. */
    public function profit(Request $request, MutationLedger $ledger): View
    {
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        $from = $request->date('date_from')?->startOfDay();
        $until = $request->date('date_to')?->endOfDay();
        $now = $ledger->summary(['search' => null, 'source' => null, 'direction' => null, 'from' => null, 'until' => null]);

        return view('superadmin.deposit.profit', $ledger->profit($from, $until) + [
            'from' => $from,
            'until' => $until,
            'duitku' => $this->duitku(),
            'userBalance' => $now['user_balance'],
            'refund' => $now['refund'],
        ]);
    }

    /** Saldo asli Duitku Disbursement (disimpan 30 detik). @return array{balance?: int, effective?: int, checked_at?: string, sandbox: bool, error?: string} */
    private function duitku(): array
    {
        $client = DuitkuDisbursementClient::make();
        $key = 'mutasi:duitku-balance:'.($client->isSandbox() ? 'sandbox' : 'production');

        if ($cached = Cache::get($key)) {
            return $cached + ['sandbox' => $client->isSandbox()];
        }

        try {
            $result = $client->checkBalance();
        } catch (Throwable $e) {
            report($e);

            return ['sandbox' => $client->isSandbox(), 'error' => 'Saldo Duitku belum bisa dicek. Coba lagi sebentar.'];
        }

        if (($result['responseCode'] ?? null) !== '00') {
            return ['sandbox' => $client->isSandbox(), 'error' => 'Duitku menjawab: '.($result['responseDesc'] ?? '-')];
        }

        $data = [
            'balance' => (int) round((float) ($result['balance'] ?? 0)),
            'effective' => (int) round((float) ($result['effectiveBalance'] ?? 0)),
            'checked_at' => now()->toIso8601String(),
        ];
        Cache::put($key, $data, self::BALANCE_CACHE_SECONDS);

        return $data + ['sandbox' => $client->isSandbox()];
    }
}
