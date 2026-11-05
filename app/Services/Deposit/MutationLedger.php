<?php

namespace App\Services\Deposit;

use App\Models\CatalogOrder;
use App\Models\Deposit;
use App\Models\Disbursement;
use App\Models\InvoiceCheckout;
use App\Models\InvoicePayment;
use App\Models\TicketOrder;
use App\Models\WalletWithdrawal;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi rekening Duitku bizbos (superadmin › Deposit › Mutasi), seperti mutasi di dashboard Duitku:
 *  Kredit = uang yang benar-benar dibayar lewat Duitku (status lunas, termasuk yang "perlu refund" karena uangnya tetap masuk).
 *  Debit  = uang yang benar-benar keluar lewat Duitku Disbursement (transfer berhasil).
 *
 * Sumber diambil langsung dari tabel transaksi masing-masing (bukan dari saldo user), satu baris = satu pembayaran/transfer:
 *  - deposits            : Top up saldo (awalan selain TIX/ORD/INV/TGH)
 *  - invoice_payments    : Invoice (INV) — Dashboard, API, Google Form (kolom invoices.source)
 *  - invoice_checkouts   : Bayar beberapa tagihan sekaligus dari portal customer (TGH)
 *  - ticket_orders       : Tiket / pendaftaran event berbayar (TIX; form pendaftaran = "Form Pembeli" di page Tiket)
 *  - catalog_orders      : Toko online / katalog, hanya yang bayar online (ORD)
 *  - wallet_withdrawals  : Tarik saldo user ke rekeningnya (Debit)
 *  - disbursements       : Disbursement owner, dari dashboard / API (Debit)
 *
 * Catatan: potongan Duitku sendiri (MDR & biaya transfer) tidak dikirim Duitku per transaksi, jadi saldo di sini
 * adalah "saldo tercatat" (bruto). Selisih dengan saldo asli Duitku = potongan Duitku + settlement yang belum cair.
 */
class MutationLedger
{
    public const SOURCES = [
        'topup' => ['Top Up Saldo', 'credit'],
        'invoice_dashboard' => ['Invoice · Dashboard', 'credit'],
        'invoice_api' => ['Invoice · API', 'credit'],
        'invoice_form' => ['Invoice · Google Form', 'credit'],
        'invoice_bulk' => ['Bayar Beberapa Tagihan', 'credit'],
        'ticket' => ['Tiket / Pendaftaran Event', 'credit'],
        'catalog' => ['Toko Online', 'credit'],
        'withdrawal' => ['Tarik Saldo', 'debit'],
        'disbursement' => ['Disbursement', 'debit'],
    ];

    public static function label(string $source): string
    {
        return self::SOURCES[$source][0] ?? $source;
    }

    /** Semua baris mutasi (belum difilter), kolom seragam untuk UNION. */
    public function rows(): Builder
    {
        $credit = "CAST('credit' AS CHAR(6))";
        $debit = "CAST('debit' AS CHAR(6))";

        $topUp = DB::table('deposits as d')->join('users as u', 'u.id', '=', 'd.user_id')
            ->where('d.status', Deposit::STATUS_SUCCESS)->whereNotNull('d.paid_at')
            ->selectRaw("d.paid_at AS at, {$credit} AS direction, CAST('topup' AS CHAR(20)) AS source, d.reference_number AS reference,
                u.name AS party, u.email AS party_note, d.payment_method AS method, d.amount AS amount, 0 AS fee, CAST(NULL AS CHAR(20)) AS flag");

        $invoices = DB::table('invoice_payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'i.company_id')
            ->whereIn('p.status', [InvoicePayment::STATUS_PAID, InvoicePayment::STATUS_REFUND_NEEDED])->whereNotNull('p.paid_at')
            ->selectRaw("p.paid_at AS at, {$credit} AS direction,
                CAST(CASE i.source WHEN 'api' THEN 'invoice_api' WHEN 'form' THEN 'invoice_form' ELSE 'invoice_dashboard' END AS CHAR(20)) AS source,
                i.number AS reference, c.name AS party, i.customer_name AS party_note, p.payment_method AS method, p.amount AS amount,
                CASE WHEN p.status = ? THEN i.fee ELSE 0 END AS fee,
                CASE WHEN p.status = ? THEN 'refund' END AS flag", [InvoicePayment::STATUS_PAID, InvoicePayment::STATUS_REFUND_NEEDED]);

        $bulk = DB::table('invoice_checkouts as k')->join('user_branches as b', 'b.id', '=', 'k.branch_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'b.company_id')->leftJoin('users as u', 'u.id', '=', 'k.user_id')
            ->whereIn('k.status', [InvoiceCheckout::STATUS_PAID, InvoiceCheckout::STATUS_REFUND_NEEDED])->whereNotNull('k.paid_at')
            ->selectRaw("k.paid_at AS at, {$credit} AS direction, CAST('invoice_bulk' AS CHAR(20)) AS source, k.merchant_order_id AS reference,
                c.name AS party, u.name AS party_note, k.payment_method AS method, k.amount AS amount, k.fee AS fee,
                CASE WHEN k.status = ? THEN 'refund' END AS flag", [InvoiceCheckout::STATUS_REFUND_NEEDED]);

        // Tiket gratis (total 0) tidak lewat Duitku.
        $tickets = DB::table('ticket_orders as t')->leftJoin('user_companies as c', 'c.id', '=', 't.company_id')
            ->whereIn('t.status', [TicketOrder::STATUS_PAID, TicketOrder::STATUS_REFUND_NEEDED])->whereNotNull('t.paid_at')->where('t.total', '>', 0)
            ->selectRaw("t.paid_at AS at, {$credit} AS direction, CAST('ticket' AS CHAR(20)) AS source, t.reference AS reference,
                c.name AS party, t.name AS party_note, t.payment_method AS method, t.total AS amount,
                CASE WHEN t.status = ? THEN t.fee ELSE 0 END AS fee,
                CASE WHEN t.status = ? THEN 'refund' END AS flag", [TicketOrder::STATUS_PAID, TicketOrder::STATUS_REFUND_NEEDED]);

        // Transfer manual ke rekening toko tidak lewat Duitku, jadi tidak dihitung.
        $catalog = DB::table('catalog_orders as o')->leftJoin('user_companies as c', 'c.id', '=', 'o.company_id')
            ->where('o.payment_type', CatalogOrder::PAYMENT_ONLINE)->whereNotNull('o.paid_at')
            ->selectRaw("o.paid_at AS at, {$credit} AS direction, CAST('catalog' AS CHAR(20)) AS source, o.reference AS reference,
                c.name AS party, o.name AS party_note, o.payment_method AS method, o.total AS amount, 0 AS fee,
                CASE WHEN o.payment_note IS NOT NULL THEN 'check' END AS flag");

        // Debit = yang ditransfer ke rekening (net). Biaya tarik/disbursement tetap di Duitku bizbos -> kolom fee.
        $withdrawals = DB::table('wallet_withdrawals as w')->join('users as u', 'u.id', '=', 'w.user_id')
            ->where('w.status', WalletWithdrawal::STATUS_SUCCESS)
            ->selectRaw("COALESCE(w.processed_at, w.updated_at) AS at, {$debit} AS direction, CAST('withdrawal' AS CHAR(20)) AS source, w.reference AS reference,
                u.name AS party, CONCAT(w.bank_name, ' •••', w.account_last4, ' a.n. ', w.account_name) AS party_note, CAST(NULL AS CHAR(20)) AS method,
                w.net_amount AS amount, w.fee AS fee, CAST(NULL AS CHAR(20)) AS flag");

        $disbursements = DB::table('disbursements as s')->join('user_branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'b.company_id')
            ->where('s.status', Disbursement::STATUS_SUCCESS)
            ->selectRaw("COALESCE(s.processed_at, s.updated_at) AS at, {$debit} AS direction, CAST('disbursement' AS CHAR(20)) AS source, s.reference AS reference,
                c.name AS party, CONCAT(s.bank_name, ' •••', s.account_last4, ' a.n. ', s.account_name) AS party_note, CAST(UPPER(s.source) AS CHAR(20)) AS method,
                s.net_amount AS amount, s.fee AS fee, CAST(NULL AS CHAR(20)) AS flag");

        return DB::query()->fromSub(
            $topUp->unionAll($invoices)->unionAll($bulk)->unionAll($tickets)->unionAll($catalog)->unionAll($withdrawals)->unionAll($disbursements),
            'm'
        );
    }

    /** @param array{search?: ?string, source?: ?string, direction?: ?string, from?: ?CarbonInterface, until?: ?CarbonInterface} $filters */
    public function filtered(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->rows()
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('m.source', $source))
            ->when($filters['direction'] ?? null, fn ($q, $direction) => $q->where('m.direction', $direction))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('m.reference', 'like', "%{$search}%")
                ->orWhere('m.party', 'like', "%{$search}%")
                ->orWhere('m.party_note', 'like', "%{$search}%")))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('m.at', '>=', $from))
            ->when($filters['until'] ?? null, fn ($q, $until) => $q->where('m.at', '<=', $until));
    }

    /**
     * Halaman tabel, terbaru di atas. Saldo Awal/Akhir dihitung dari SEMUA mutasi (bukan hanya yang terfilter),
     * seperti rekening koran: saldo akhir baris = total kredit - debit sampai baris itu.
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $page = $this->filtered($filters)->orderByDesc('m.at')->orderByDesc('m.reference')->paginate($perPage)->withQueryString();

        // Dengan filter sumber/arah/cari ada baris yang disembunyikan, jadi saldo per baris tidak ditampilkan.
        if (! $this->narrowed($filters) && ($top = $page->getCollection()->first())) {
            $balance = $this->balanceUntil($top->at, $top->reference);

            $page->getCollection()->each(function ($row) use (&$balance) {
                $delta = $row->direction === 'credit' ? (int) $row->amount : -(int) $row->amount;
                $row->balance_after = $balance;
                $row->balance_before = $balance - $delta;
                $balance = $row->balance_before;
            });
        }

        return $page;
    }

    /** Ringkasan untuk filter yang sama (tanpa filter arah, supaya kredit & debit selalu tampil). */
    public function summary(array $filters): array
    {
        $totals = $this->filtered(['direction' => null] + $filters)->selectRaw("
            COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount END), 0) AS credit,
            SUM(CASE WHEN direction = 'credit' THEN 1 ELSE 0 END) AS credit_count,
            COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount END), 0) AS debit,
            SUM(CASE WHEN direction = 'debit' THEN 1 ELSE 0 END) AS debit_count,
            COALESCE(SUM(fee), 0) AS fee,
            COALESCE(SUM(CASE WHEN flag = 'refund' THEN amount END), 0) AS refund,
            SUM(CASE WHEN flag = 'refund' THEN 1 ELSE 0 END) AS refund_count")->first();

        $perSource = $this->filtered(['direction' => null] + $filters)->groupBy('m.source')
            ->selectRaw('m.source, COUNT(*) AS trx, COALESCE(SUM(amount), 0) AS total')->pluck('total', 'source')->map(fn ($v) => (int) $v);

        return [
            'credit' => (int) $totals->credit, 'credit_count' => (int) $totals->credit_count,
            'debit' => (int) $totals->debit, 'debit_count' => (int) $totals->debit_count,
            'fee' => (int) $totals->fee,
            'refund' => (int) $totals->refund, 'refund_count' => (int) $totals->refund_count,
            'per_source' => $perSource,
            // Saldo tercatat per akhir rentang (atau saat ini), dari semua sumber.
            'balance' => $this->balanceUntil($filters['until'] ?? null),
            // Transfer yang hasilnya belum pasti: bisa jadi uangnya sudah keluar dari Duitku.
            'review' => [
                'withdrawal' => WalletWithdrawal::where('status', WalletWithdrawal::STATUS_NEEDS_REVIEW)->count(),
                'disbursement' => Disbursement::where('status', Disbursement::STATUS_NEEDS_REVIEW)->count(),
            ],
            'user_balance' => (int) DB::table('wallets')->sum('balance'),
        ];
    }

    /** Total kredit - debit sampai waktu tertentu (inklusif). Dengan $reference: urutan sama dengan tabel (waktu, lalu referensi). */
    public function balanceUntil(mixed $at = null, ?string $reference = null): int
    {
        return (int) $this->rows()
            ->when($at && $reference !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('m.at', '<', $at)
                ->orWhere(fn ($same) => $same->where('m.at', '=', $at)->where('m.reference', '<=', $reference))))
            ->when($at && $reference === null, fn ($q) => $q->where('m.at', '<=', $at))
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -CAST(amount AS SIGNED) END), 0) AS balance")
            ->value('balance');
    }

    private function narrowed(array $filters): bool
    {
        return filled($filters['source'] ?? null) || filled($filters['direction'] ?? null) || filled(trim((string) ($filters['search'] ?? '')));
    }
}
