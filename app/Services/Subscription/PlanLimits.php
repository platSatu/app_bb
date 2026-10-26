<?php

namespace App\Services\Subscription;

use App\Exceptions\PlanLimitException;
use App\Models\BranchSubscription;
use App\Models\Invoice;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\UserBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat aturan batas paket (diisi superadmin di form Paket, disalin ke branch saat paket diaktifkan):
 * - pages    : jumlah page per kategori page            {"<page_type_id>": n}
 * - invoices : invoice dibuat per bulan (semua sumber)   {"monthly": n}
 * - whatsapp : semua pesan keluar per bulan              {"monthly": n}
 * Key tidak ada = tanpa batas. Bulan = bulan kalender, reset otomatis tanggal 1.
 * Semua pengecekan dikunci (baris branch / baris pemakaian) supaya request bersamaan tidak bisa melewati batas.
 */
class PlanLimits
{
    public const MAX_VALUE = 1000000;

    /** Batas satu key di paket aktif branch. null = tanpa batas (termasuk langganan lama sebelum fitur ini). */
    public function limit(string $branchId, string $app, string $key): ?int
    {
        $limits = BranchSubscription::where('branch_id', $branchId)->where('app_key', $app)->where('ends_at', '>', now())->value('limits');

        return isset($limits[$key]) ? (int) $limits[$key] : null;
    }

    /** Membuat page baru / memindah page ke kategori lain. Wajib dipanggil di dalam DB::transaction. */
    public function assertPage(string $branchId, string $pageTypeId, ?string $ignorePageId = null): void
    {
        if (($max = $this->limit($branchId, 'pages', $pageTypeId)) === null) {
            return;
        }

        $this->lockBranch($branchId);
        $used = PageCategory::where('branch_id', $branchId)->where('page_type_id', $pageTypeId)
            ->when($ignorePageId, fn ($q, $id) => $q->whereKeyNot($id))->count();

        if ($used >= $max) {
            $type = PageType::whereKey($pageTypeId)->value('name') ?? 'ini';

            throw new PlanLimitException("Paket Anda hanya bisa membuat {$max} page kategori {$type} di branch ini. Hapus page lama yang tidak dipakai, atau upgrade paket.");
        }
    }

    /** Membuat $count invoice baru (manual, batch, API, Google Form). Wajib dipanggil di dalam DB::transaction. */
    public function assertInvoices(string $branchId, int $count = 1): void
    {
        if (($max = $this->limit($branchId, 'invoices', 'monthly')) === null) {
            return;
        }

        $this->lockBranch($branchId);
        $used = Invoice::where('branch_id', $branchId)->where('created_at', '>=', now()->startOfMonth())->count();

        if ($used + $count > $max) {
            throw new PlanLimitException("Kuota invoice bulan ini sudah habis ({$this->number($used)} dari {$this->number($max)}). Kuota kembali tanggal 1 bulan depan, atau upgrade paket.");
        }
    }

    /**
     * Pakai kuota pesan WhatsApp sebelum dikirim. $partial = true (broadcast): ambil sebanyak sisa kuota.
     * @return int jumlah pesan yang boleh dikirim (0 = kuota habis)
     */
    public function takeMessages(string $branchId, int $wanted, bool $partial = false): int
    {
        if ($wanted <= 0) {
            return 0;
        }

        $max = $this->limit($branchId, 'whatsapp', 'monthly');

        return DB::transaction(function () use ($branchId, $wanted, $partial, $max) {
            $row = $this->usageRow($branchId, 'whatsapp');
            $room = $max === null ? $wanted : max(0, $max - $row->used);
            $granted = $room >= $wanted ? $wanted : ($partial ? $room : 0);

            if ($granted > 0) {
                DB::table('plan_usages')->where('id', $row->id)->update(['used' => DB::raw('used + '.(int) $granted), 'updated_at' => now()]);
            }

            return $granted;
        });
    }

    /** Kembalikan kuota pesan yang ternyata gagal dikirim. */
    public function refundMessages(string $branchId, int $count): void
    {
        DB::table('plan_usages')->where(['branch_id' => $branchId, 'metric' => 'whatsapp', 'period' => now()->format('Y-m')])
            ->where('used', '>=', $count)->decrement('used', $count);
    }

    public function messagesExhaustedMessage(string $branchId): string
    {
        $max = (int) $this->limit($branchId, 'whatsapp', 'monthly');

        return "Kuota pesan WhatsApp bulan ini sudah habis ({$this->number($max)} pesan). Kuota kembali tanggal 1 bulan depan, atau upgrade paket.";
    }

    /** Ringkasan pemakaian untuk owner, per layanan, mis. ['invoices' => ['Invoice bulan ini: 120 / 300']]. Hanya yang dibatasi. */
    public function summary(string $branchId): array
    {
        $limits = BranchSubscription::where('branch_id', $branchId)->where('ends_at', '>', now())->pluck('limits', 'app_key');
        $since = now()->startOfMonth();
        $summary = [];

        if ($pages = $limits['pages'] ?? null) {
            $used = PageCategory::where('branch_id', $branchId)->whereIn('page_type_id', array_keys($pages))->selectRaw('page_type_id, COUNT(*) AS total')->groupBy('page_type_id')->pluck('total', 'page_type_id');
            $names = PageType::whereKey(array_keys($pages))->pluck('name', 'id');
            foreach ($pages as $typeId => $max) {
                $summary['pages'][] = ($names[$typeId] ?? 'Page').': '.(int) ($used[$typeId] ?? 0).' / '.$max;
            }
        }

        if (isset($limits['invoices']['monthly'])) {
            $used = Invoice::where('branch_id', $branchId)->where('created_at', '>=', $since)->count();
            $summary['invoices'][] = 'Invoice bulan ini: '.$this->number($used).' / '.$this->number($limits['invoices']['monthly']);
        }

        if (isset($limits['whatsapp']['monthly'])) {
            $used = (int) DB::table('plan_usages')->where(['branch_id' => $branchId, 'metric' => 'whatsapp', 'period' => $since->format('Y-m')])->value('used');
            $summary['whatsapp'][] = 'Pesan bulan ini: '.$this->number($used).' / '.$this->number($limits['whatsapp']['monthly']);
        }

        return $summary;
    }

    /** Hanya angka 0..MAX_VALUE yang disimpan; isian kosong dibuang (= tanpa batas). */
    public static function clean(mixed $limits): array
    {
        return collect(is_array($limits) ? $limits : [])
            ->filter(fn ($value, $key) => is_string($key) && is_numeric($value) && $value >= 0 && $value <= self::MAX_VALUE)
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    /** Diperpanjang sebelum habis: ambil yang lebih longgar (key yang tidak dibatasi salah satu paket = tanpa batas). */
    public static function looser(array $current, array $incoming): array
    {
        $merged = [];

        foreach (array_intersect_key($current, $incoming) as $key => $value) {
            $merged[$key] = max((int) $value, (int) $incoming[$key]);
        }

        return $merged;
    }

    private function lockBranch(string $branchId): void
    {
        UserBranch::whereKey($branchId)->lockForUpdate()->first();
    }

    /** Baris pemakaian bulan ini, dibuat kalau belum ada, lalu dikunci. */
    private function usageRow(string $branchId, string $metric): object
    {
        $keys = ['branch_id' => $branchId, 'metric' => $metric, 'period' => now()->format('Y-m')];
        DB::table('plan_usages')->insertOrIgnore([...$keys, 'id' => (string) Str::uuid(), 'used' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('plan_usages')->where($keys)->lockForUpdate()->first();
    }

    private function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
