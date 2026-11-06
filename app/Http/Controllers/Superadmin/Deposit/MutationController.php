<?php

namespace App\Http\Controllers\Superadmin\Deposit;

use App\Http\Controllers\Controller;
use App\Models\PaymentCallback;
use App\Services\Deposit\MutationLedger;
use App\Services\Payment\DuitkuDisbursementClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'], 'customer' => ['nullable', 'string', 'max:100']]);
        $from = $request->date('date_from')?->startOfDay();
        $until = $request->date('date_to')?->endOfDay();
        $now = $ledger->summary(['search' => null, 'source' => null, 'direction' => null, 'from' => null, 'until' => null]);

        return view('superadmin.deposit.profit', $ledger->profit($from, $until, trim((string) $request->query('customer'))) + [
            'from' => $from,
            'until' => $until,
            'duitku' => $this->duitku(),
            'userBalance' => $now['user_balance'],
            'refund' => $now['refund'],
        ]);
    }

    /**
     * Tab Log (hanya baca): callback Duitku dari semua aplikasi & webhook bizbos ke aplikasi pelanggan.
     * Isi mentah (payload, tanda tangan, balasan server pelanggan) sengaja tidak ditampilkan.
     */
    public function logs(Request $request): View
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:callback,webhook'],
            'search' => ['nullable', 'string', 'max:60'],
            'result' => ['nullable', 'in:ok,problem'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
        $type = $data['type'] ?? 'callback';
        $search = trim((string) ($data['search'] ?? ''));
        $from = $request->date('date_from')?->startOfDay();
        $until = $request->date('date_to')?->endOfDay();
        $okResults = ['paid', 'already_processed'];

        $logs = $type === 'callback'
            ? PaymentCallback::query()
                ->when($search !== '', fn ($q) => $q->where('merchant_order_id', 'like', "%{$search}%"))
                ->when(($data['result'] ?? null) === 'ok', fn ($q) => $q->whereIn('result', $okResults))
                ->when(($data['result'] ?? null) === 'problem', fn ($q) => $q->where(fn ($w) => $w->whereNotIn('result', $okResults)->orWhereNull('result')))
                ->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($until, fn ($q) => $q->where('created_at', '<=', $until))
                ->latest()->paginate(15)->withQueryString()
            : DB::table('invoice_webhook_logs as l')
                ->join('invoice_api_clients as a', 'a.id', '=', 'l.api_client_id')
                ->join('user_branches as b', 'b.id', '=', 'a.branch_id')->leftJoin('user_companies as c', 'c.id', '=', 'b.company_id')
                ->leftJoin('invoices as i', 'i.id', '=', 'l.invoice_id')->leftJoin('disbursements as d', 'd.id', '=', 'l.disbursement_id')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('i.number', 'like', "%{$search}%")
                    ->orWhere('i.external_id', 'like', "%{$search}%")->orWhere('d.reference', 'like', "%{$search}%")->orWhere('c.name', 'like', "%{$search}%")))
                ->when(($data['result'] ?? null) === 'ok', fn ($q) => $q->whereBetween('l.http_status', [200, 299]))
                ->when(($data['result'] ?? null) === 'problem', fn ($q) => $q->where(fn ($w) => $w->whereNull('l.http_status')->orWhereNotBetween('l.http_status', [200, 299])))
                ->when($from, fn ($q) => $q->where('l.created_at', '>=', $from))->when($until, fn ($q) => $q->where('l.created_at', '<=', $until))
                ->select('l.created_at', 'l.event', 'l.attempt', 'l.http_status', 'l.error', 'c.name as company', 'a.webhook_url',
                    'i.number as invoice_number', 'i.external_id', 'd.reference as payout_reference')
                ->orderByDesc('l.created_at')->paginate(15)->withQueryString();

        return view('superadmin.deposit.logs', ['logs' => $logs, 'type' => $type]);
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
