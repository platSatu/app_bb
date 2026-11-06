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
 *  Debit  = uang yang benar-benar keluar lewat Duitku Disbursement (transfer berhasil): nominal transfer + biaya Duitku.
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
        // Uang masuk = yang benar-benar masuk Duitku: bruto - fee yang dipotong Duitku (QRIS, ditanggung bizbos),
        // dari catatan saldonya (kunci idempotency sama persis dengan saat dikreditkan). Transaksi lama: fee kosong = bruto.
        $wallet = fn (Builder $q, string $key) => $q->leftJoin('wallet_transactions as wt', 'wt.idempotency_key', '=', DB::raw($key));
        $net = fn (string $gross) => "{$gross} - COALESCE(wt.duitku_fee, 0) AS amount, 0 AS transfer, COALESCE(wt.duitku_fee, 0) AS duitku_fee, wt.available_at AS settles_at";
        $noSettle = 'CAST(NULL AS DATETIME) AS settles_at';

        $topUp = $wallet(DB::table('deposits as d')->join('users as u', 'u.id', '=', 'd.user_id'), "CONCAT('deposit:', d.id)")
            ->where('d.status', Deposit::STATUS_SUCCESS)->whereNotNull('d.paid_at')
            ->selectRaw("d.paid_at AS at, {$credit} AS direction, CAST('topup' AS CHAR(20)) AS source, d.reference_number AS reference,
                u.name AS party, u.email AS party_note, d.payment_method AS method, {$net('d.amount')}, CAST(NULL AS CHAR(20)) AS flag");

        $invoices = $wallet(DB::table('invoice_payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'i.company_id'), "CONCAT('invoice-payment:', p.id)")
            ->whereIn('p.status', [InvoicePayment::STATUS_PAID, InvoicePayment::STATUS_REFUND_NEEDED])->whereNotNull('p.paid_at')
            ->selectRaw("p.paid_at AS at, {$credit} AS direction,
                CAST(CASE i.source WHEN 'api' THEN 'invoice_api' WHEN 'form' THEN 'invoice_form' ELSE 'invoice_dashboard' END AS CHAR(20)) AS source,
                i.number AS reference, c.name AS party, i.customer_name AS party_note, p.payment_method AS method, {$net('p.amount')},
                CASE WHEN p.status = ? THEN 'refund' END AS flag", [InvoicePayment::STATUS_REFUND_NEEDED]);

        $bulk = $wallet(DB::table('invoice_checkouts as k')->join('user_branches as b', 'b.id', '=', 'k.branch_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'b.company_id')->leftJoin('users as u', 'u.id', '=', 'k.user_id'), "CONCAT('invoice-checkout:', k.id)")
            ->whereIn('k.status', [InvoiceCheckout::STATUS_PAID, InvoiceCheckout::STATUS_REFUND_NEEDED])->whereNotNull('k.paid_at')
            ->selectRaw("k.paid_at AS at, {$credit} AS direction, CAST('invoice_bulk' AS CHAR(20)) AS source, k.merchant_order_id AS reference,
                c.name AS party, u.name AS party_note, k.payment_method AS method, {$net('k.amount')},
                CASE WHEN k.status = ? THEN 'refund' END AS flag", [InvoiceCheckout::STATUS_REFUND_NEEDED]);

        // Tiket gratis (total 0) tidak lewat Duitku.
        $tickets = $wallet(DB::table('ticket_orders as t')->leftJoin('user_companies as c', 'c.id', '=', 't.company_id'), "CONCAT('ticket:', t.id)")
            ->whereIn('t.status', [TicketOrder::STATUS_PAID, TicketOrder::STATUS_REFUND_NEEDED])->whereNotNull('t.paid_at')->where('t.total', '>', 0)
            ->selectRaw("t.paid_at AS at, {$credit} AS direction, CAST('ticket' AS CHAR(20)) AS source, t.reference AS reference,
                c.name AS party, t.name AS party_note, t.payment_method AS method, {$net('t.total')},
                CASE WHEN t.status = ? THEN 'refund' END AS flag", [TicketOrder::STATUS_REFUND_NEEDED]);

        // Transfer manual ke rekening toko tidak lewat Duitku, jadi tidak dihitung.
        $catalog = $wallet(DB::table('catalog_orders as o')->leftJoin('user_companies as c', 'c.id', '=', 'o.company_id'), "CONCAT('catalog:', o.id)")
            ->where('o.payment_type', CatalogOrder::PAYMENT_ONLINE)->whereNotNull('o.paid_at')
            ->selectRaw("o.paid_at AS at, {$credit} AS direction, CAST('catalog' AS CHAR(20)) AS source, o.reference AS reference,
                c.name AS party, o.name AS party_note, o.payment_method AS method, {$net('o.total')},
                CASE WHEN o.payment_note IS NOT NULL THEN 'check' END AS flag");

        // Debit = yang ditransfer ke rekening (net) + biaya Duitku per transfer.
        $withdrawals = DB::table('wallet_withdrawals as w')->join('users as u', 'u.id', '=', 'w.user_id')
            ->where('w.status', WalletWithdrawal::STATUS_SUCCESS)
            ->selectRaw("COALESCE(w.processed_at, w.updated_at) AS at, {$debit} AS direction, CAST('withdrawal' AS CHAR(20)) AS source, w.reference AS reference,
                u.name AS party, CONCAT(w.bank_name, ' •••', w.account_last4, ' a.n. ', w.account_name) AS party_note, CAST(NULL AS CHAR(20)) AS method,
                w.net_amount + COALESCE(w.duitku_fee, 0) AS amount, w.net_amount AS transfer, COALESCE(w.duitku_fee, 0) AS duitku_fee, {$noSettle}, CAST(NULL AS CHAR(20)) AS flag");

        $disbursements = DB::table('disbursements as s')->join('user_branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoin('user_companies as c', 'c.id', '=', 'b.company_id')
            ->where('s.status', Disbursement::STATUS_SUCCESS)
            ->selectRaw("COALESCE(s.processed_at, s.updated_at) AS at, {$debit} AS direction, CAST('disbursement' AS CHAR(20)) AS source, s.reference AS reference,
                c.name AS party, CONCAT(s.bank_name, ' •••', s.account_last4, ' a.n. ', s.account_name) AS party_note, CAST(UPPER(s.source) AS CHAR(20)) AS method,
                s.net_amount + COALESCE(s.duitku_fee, 0) AS amount, s.net_amount AS transfer, COALESCE(s.duitku_fee, 0) AS duitku_fee, {$noSettle}, CAST(NULL AS CHAR(20)) AS flag");

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
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
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
            COALESCE(SUM(CASE WHEN flag = 'refund' THEN amount END), 0) AS refund,
            SUM(CASE WHEN flag = 'refund' THEN 1 ELSE 0 END) AS refund_count")->first();

        $perSource = $this->filtered(['direction' => null] + $filters)->groupBy('m.source')
            ->selectRaw('m.source, COUNT(*) AS trx, COALESCE(SUM(amount), 0) AS total')->pluck('total', 'source')->map(fn ($v) => (int) $v);

        return [
            'credit' => (int) $totals->credit, 'credit_count' => (int) $totals->credit_count,
            'debit' => (int) $totals->debit, 'debit_count' => (int) $totals->debit_count,
            'refund' => (int) $totals->refund, 'refund_count' => (int) $totals->refund_count,
            'per_source' => $perSource,
            // Saldo tercatat per akhir rentang (atau saat ini), dari semua sumber.
            'balance' => $balance = $this->balanceUntil($filters['until'] ?? null),
            // Saldo tercatat saat ini (untuk dibandingkan dengan saldo asli Duitku), tidak terpengaruh filter tanggal.
            'balance_now' => isset($filters['until']) ? $this->balanceUntil() : $balance,
            // Transfer yang hasilnya belum pasti: bisa jadi uangnya sudah keluar dari Duitku.
            'review' => [
                'withdrawal' => WalletWithdrawal::where('status', WalletWithdrawal::STATUS_NEEDS_REVIEW)->count(),
                'disbursement' => Disbursement::where('status', Disbursement::STATUS_NEEDS_REVIEW)->count(),
            ],
            'user_balance' => (int) DB::table('wallets')->sum('balance'),
            // Uang masuk yang belum cair di Duitku (perkiraan tanggal cair per metode, Payment Gateway › settlement).
            'pending' => $this->pending(),
        ];
    }

    /** Uang masuk yang belum cair saat ini. Saldo Duitku yang sebanding = saldo tercatat - ini. */
    public function pending(): int
    {
        return (int) $this->rows()->where('m.direction', 'credit')->where('m.settles_at', '>', now())->sum('m.amount');
    }

    /** Aplikasi di tab Laba & Neraca (urutan tampil). */
    public const APPS = [
        'topup' => 'Top Up Saldo',
        'invoice_dashboard' => 'Invoice · Dashboard',
        'invoice_api' => 'Invoice · API',
        'invoice_form' => 'Invoice · Google Form',
        'invoice_bulk' => 'Bayar Beberapa Tagihan',
        'ticket' => 'Ticketing',
        'catalog' => 'Toko Online',
        'withdrawal' => 'Tarik Saldo',
        'disbursement' => 'Disbursement',
        'referral' => 'Bonus Referral (biaya)',
    ];

    /** Pilihan filter aplikasi: aplikasi transaksi + sewa per kategori aplikasi yang ada. @return array<string, string> */
    public static function appOptions(): array
    {
        $rentals = DB::table('category_applications')->orderBy('name')->pluck('name')
            ->mapWithKeys(fn ($name) => ['sewa:'.$name => 'Sewa '.$name])->all();

        return array_diff_key(self::APPS, ['referral' => 1]) + $rentals + ['referral' => self::APPS['referral']];
    }

    /** Nama tampilan aplikasi; sewa paket = "Sewa <aplikasi>". */
    public static function appLabel(string $app): string
    {
        return str_starts_with($app, 'sewa:') ? 'Sewa '.substr($app, 5) : (self::APPS[$app] ?? $app);
    }

    /**
     * Laba bizbos per aplikasi & per pelanggan untuk satu periode. Sumber:
     *  - pembayaran Duitku: riwayat saldo (satu pintu creditPayment) -> bruto, potongan payment gateway, masuk saldo;
     *    fee layanan = bruto - potongan - masuk saldo; biaya Duitku = potongan QRIS default (tarif Duitku) bila dibayar QRIS;
     *  - transfer: tarik saldo & disbursement berhasil -> fee user - biaya Duitku per transfer;
     *  - langganan: pembelian paket dari saldo; bonus referral = biaya (mengurangi laba).
     * Koreksi saldo admin bukan laba, ditampilkan terpisah.
     *
     * @return array{apps: \Illuminate\Support\Collection, customers: \Illuminate\Support\Collection, adjustment: array{in: int, out: int}}
     */
    public function profit(?CarbonInterface $from, ?CarbonInterface $until, ?string $userId = null, ?string $app = null): array
    {
        $gateway = \App\Models\PaymentGatewaySetting::duitku();
        $qris = "'".implode("','", array_map(fn ($m) => preg_replace('/[^A-Z0-9]/', '', $m), $gateway->qrisMethods()))."'";
        $pct = (float) $gateway->qrisFeePercent();
        $period = fn ($q, string $column) => $q->when($from, fn ($q) => $q->where($column, '>=', $from))->when($until, fn ($q) => $q->where($column, '<=', $until));
        $num = fn (string $expr) => "CAST({$expr} AS SIGNED)";

        // Kolom seragam: app, user_id, trx, gross (nilai), service (fee layanan), gateway (potongan PG), duitku (biaya Duitku), extra (laba lain).
        $payments = $period(DB::table('wallet_transactions as w')
            ->leftJoin('invoices as i', fn ($j) => $j->on('i.id', '=', 'w.reference_id')->where('w.reference_type', \App\Models\Invoice::class))
            ->where('w.direction', 'credit')->whereNotNull('w.paid_amount')
            ->whereIn('w.type', ['deposit', 'invoice_payment', 'ticket_sale', 'catalog_sale']), 'w.created_at')
            ->selectRaw("CAST(CASE w.type WHEN 'deposit' THEN 'topup' WHEN 'ticket_sale' THEN 'ticket' WHEN 'catalog_sale' THEN 'catalog'
                    ELSE CASE WHEN w.reference_type = ? THEN 'invoice_bulk'
                        WHEN i.source = 'api' THEN 'invoice_api' WHEN i.source = 'form' THEN 'invoice_form' ELSE 'invoice_dashboard' END END AS CHAR(160)) AS app,
                w.user_id, 1 AS trx, {$num('w.paid_amount')} AS gross,
                {$num('w.paid_amount')} - {$num('w.amount')} - {$num('w.method_fee')} AS service, {$num('w.method_fee')} AS gateway,
                COALESCE(w.duitku_fee, CASE WHEN w.payment_method IN ({$qris}) THEN CEIL(w.paid_amount * {$pct} / 100) ELSE 0 END) AS duitku, 0 AS extra", [InvoiceCheckout::class]);

        $withdrawals = $period(DB::table('wallet_withdrawals as x')->where('x.status', WalletWithdrawal::STATUS_SUCCESS), 'x.processed_at')
            ->selectRaw("CAST('withdrawal' AS CHAR(160)) AS app, x.user_id, 1 AS trx, {$num('x.amount')} AS gross, {$num('x.fee')} AS service, 0 AS gateway,
                {$num('COALESCE(x.duitku_fee, 0)')} AS duitku, 0 AS extra");

        $disbursements = $period(DB::table('disbursements as x')->join('user_branches as b', 'b.id', '=', 'x.branch_id')
            ->where('x.status', Disbursement::STATUS_SUCCESS), 'x.processed_at')
            ->selectRaw("CAST('disbursement' AS CHAR(160)) AS app, b.user_id, 1 AS trx, {$num('x.amount')} AS gross, {$num('x.fee')} AS service, 0 AS gateway,
                {$num('COALESCE(x.duitku_fee, 0)')} AS duitku, 0 AS extra");

        // Paket dibayar dari saldo: seluruhnya pendapatan. Bonus referral: biaya bizbos (negatif).
        // Sewa: pembelian paket dipecah per aplikasi yang disewa (paket -> kategori aplikasi), app = "sewa:<nama aplikasi>".
        $wallet = $period(DB::table('wallet_transactions as w')->whereIn('w.type', ['purchase', 'referral_bonus'])
            ->leftJoin('vouchers as v', fn ($j) => $j->on('v.id', '=', 'w.reference_id')->where('w.reference_type', \App\Models\Voucher::class))
            ->leftJoin('packages as k', 'k.id', '=', 'v.package_id')->leftJoin('category_applications as ca', 'ca.id', '=', 'k.category_application_id'), 'w.created_at')
            ->selectRaw("CAST(CASE w.type WHEN 'purchase' THEN CONCAT('sewa:', COALESCE(ca.name, 'Lainnya')) ELSE 'referral' END AS CHAR(160)) AS app, w.user_id, 1 AS trx,
                {$num('w.amount')} AS gross, 0 AS service, 0 AS gateway, 0 AS duitku,
                CASE w.type WHEN 'purchase' THEN {$num('w.amount')} ELSE -{$num('w.amount')} END AS extra");

        // Filter pelanggan & aplikasi berlaku untuk kedua tabel (per aplikasi & per pelanggan).
        $all = DB::query()->fromSub($payments->unionAll($withdrawals)->unionAll($disbursements)->unionAll($wallet), 'p')
            ->when($userId, fn ($q) => $q->where('p.user_id', $userId))
            ->when($app, fn ($q) => $q->where('p.app', $app));
        $sum = 'COUNT(*) AS trx, SUM(gross) AS gross, SUM(service) AS service, SUM(gateway) AS gateway, SUM(duitku) AS duitku,
            SUM(service + gateway - duitku + extra) AS profit';

        $apps = (clone $all)->groupBy('app')->selectRaw("app, {$sum}")->get()->keyBy('app');

        $customers = (clone $all)->leftJoin('users as u', 'u.id', '=', 'p.user_id')->leftJoin('user_companies as c', 'c.user_id', '=', 'p.user_id')
            ->groupBy('p.user_id', 'u.name', 'u.email', 'c.name')
            ->selectRaw("p.user_id, u.name, u.email, c.name AS company, {$sum}")
            ->orderByDesc('profit')->limit(50)->get();

        // Rincian per aplikasi untuk pelanggan di atas (satu pelanggan bisa punya banyak aplikasi, labanya diakumulasi).
        $perApp = (clone $all)->whereIn('p.user_id', $customers->pluck('user_id'))->groupBy('p.user_id', 'app')
            ->selectRaw('p.user_id, app, SUM(service + gateway - duitku + extra) AS profit')->get()->groupBy('user_id');
        $customers->each(fn ($c) => $c->apps = $perApp->get($c->user_id, collect())->sortByDesc('profit')->values());

        $adjustment = $period(DB::table('wallet_transactions')->where('type', 'adjustment'), 'created_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount END), 0) AS `in`, COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount END), 0) AS `out`")->first();

        return ['apps' => $apps, 'customers' => $customers, 'adjustment' => ['in' => (int) $adjustment->in, 'out' => (int) $adjustment->out]];
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
