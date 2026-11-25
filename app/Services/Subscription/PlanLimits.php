<?php

namespace App\Services\Subscription;

use App\Exceptions\PlanLimitException;
use App\Models\BranchSubscription;
use App\Models\Invoice;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\ScheduleRoutine;
use App\Models\UserBranch;
use App\Support\Apps;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya tempat aturan batas paket (diisi superadmin di form Paket, disalin ke branch saat paket diaktifkan):
 * - events   : jumlah event per kategori page tiket      {"<page_type_id>": n}
 * - pages    : jumlah page per kategori page lain        {"<page_type_id>": n}
 * - invoices : invoice dibuat per bulan (semua sumber)   {"monthly": n}
 * - whatsapp : semua pesan keluar per bulan              {"monthly": n}
 * - schedules: murid aktif saat ini (bukan per bulan)    {"students": n}
 * Key tidak ada = tanpa batas. Bulan = bulan kalender, reset otomatis tanggal 1.
 * Semua pengecekan dikunci (baris branch / baris pemakaian) supaya request bersamaan tidak bisa melewati batas.
 */
class PlanLimits
{
    public const MAX_VALUE = 1000000;

    /** Peringatan "kuota hampir penuh" mulai dari persentase ini. */
    public const WARN_PERCENT = 80;

    /** Batas satu key di paket aktif branch. null = tanpa batas (termasuk langganan lama sebelum fitur ini). */
    public function limit(string $branchId, string $app, string $key): ?int
    {
        $limits = BranchSubscription::where('branch_id', $branchId)->where('app_key', $app)->where('ends_at', '>', now())->value('limits');

        return isset($limits[$key]) ? (int) $limits[$key] : null;
    }

    /** Membuat page baru / memindah page ke kategori lain. Wajib dipanggil di dalam DB::transaction. */
    public function assertPage(string $branchId, string $pageTypeId, ?string $ignorePageId = null): void
    {
        // Kategori bertemplate tiket dibatasi paket Event, kategori lain paket Pages.
        $app = Apps::forTemplate(PageType::whereKey($pageTypeId)->value('template'));

        if (($max = $this->limit($branchId, $app, $pageTypeId)) === null) {
            return;
        }

        $this->lockBranch($branchId);
        $used = PageCategory::where('branch_id', $branchId)->where('page_type_id', $pageTypeId)
            ->when($ignorePageId, fn ($q, $id) => $q->whereKeyNot($id))->count();

        if ($used >= $max) {
            $type = PageType::whereKey($pageTypeId)->value('name') ?? 'ini';

            $noun = $app === Apps::EVENTS ? 'event' : 'page';

            throw new PlanLimitException("Paket Anda hanya bisa membuat {$max} {$noun} kategori {$type} di branch ini. Hapus {$noun} lama yang tidak dipakai, atau upgrade paket.");
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

    /** Kuota pesan WhatsApp bulan ini: max null = tanpa batas. */
    public function messageQuota(string $branchId): array
    {
        $max = $this->limit($branchId, 'whatsapp', 'monthly');
        $used = (int) DB::table('plan_usages')->where(['branch_id' => $branchId, 'metric' => 'whatsapp', 'period' => now()->format('Y-m')])->value('used');

        return ['unlimited' => $max === null, 'max' => $max, 'used' => $used, 'remaining' => $max === null ? null : max(0, $max - $used)];
    }

    public function messagesExhaustedMessage(string $branchId): string
    {
        $max = (int) $this->limit($branchId, 'whatsapp', 'monthly');

        return "Kuota pesan WhatsApp bulan ini sudah habis ({$this->number($max)} pesan). Kuota kembali tanggal 1 bulan depan, atau upgrade paket.";
    }

    /**
     * Murid aktif saat ini di branch = murid yang sedang atau akan ikut jadwal aktif yang belum berakhir.
     * Satu murid di beberapa jadwal tetap 1. Murid yang dikeluarkan dari jadwal (left_on hari ini/lewat) atau diubah
     * Tidak Aktif per hari ini langsung tidak terhitung, jadi kuotanya bisa dipakai murid lain. Salinan bulan depan tidak dobel.
     */
    public function activeStudentIds(string $branchId, ?string $exceptRoutineId = null): Collection
    {
        $today = today()->toDateString();

        return DB::table('schedule_participants as p')
            ->join('schedule_routines as r', 'r.id', '=', 'p.routine_id')
            ->join('schedule_students as s', 's.id', '=', 'p.student_id')
            ->where('r.branch_id', $branchId)
            ->where('s.branch_id', $branchId)
            ->where('r.status', ScheduleRoutine::STATUS_ACTIVE)
            // Murid Tidak Aktif terhitung sampai tanggal keluarnya (status saja tidak cukup: kalau diaktifkan lagi,
            // jadwal yang masih berjalan ikut aktif lagi tanpa lewat cek kuota).
            ->where(fn ($q) => $q->whereNull('s.left_on')->orWhere('s.left_on', '>', $today))
            ->where(fn ($q) => $q->whereNull('r.ends_on')->orWhere('r.ends_on', '>=', $today))
            // Keluar di masa depan (mis. dijadwalkan keluar akhir bulan) masih terhitung sampai tanggalnya;
            // keluar di tanggal yang sama dengan masuk = tidak pernah ikut.
            ->where(fn ($q) => $q->whereNull('p.left_on')->orWhere(fn ($w) => $w->where('p.left_on', '>', $today)
                ->where(fn ($j) => $j->whereNull('p.joined_on')->orWhereColumn('p.left_on', '>', 'p.joined_on'))))
            ->when($exceptRoutineId, fn ($q, $id) => $q->where('p.routine_id', '!=', $id))
            ->distinct()
            ->pluck('p.student_id');
    }

    /** Kuota murid aktif: null = tanpa batas. */
    public function studentQuota(string $branchId): ?array
    {
        if (($max = $this->limit($branchId, 'schedules', 'students')) === null) {
            return null;
        }

        $used = $this->activeStudentIds($branchId)->count();

        return [
            'max' => $max,
            'used' => $used,
            'remaining' => max(0, $max - $used),
            'percent' => $max > 0 ? min(100, (int) floor($used / $max * 100)) : 100,
            'full' => $used >= $max,
            'warn' => $max === 0 || $used * 100 >= $max * self::WARN_PERCENT,
        ];
    }

    /**
     * Simpan jadwal (buat, edit, Isi Murid, salin ke bulan depan). Ditolak hanya kalau jumlah murid aktif branch
     * BERTAMBAH melewati batas. Mengeluarkan / menukar murid dan menyimpan ulang tetap boleh, termasuk setelah turun
     * paket (murid lama tetap jalan). Wajib dipanggil di dalam DB::transaction, sebelum peserta disimpan.
     *
     * @param  list<string>  $studentIds  daftar murid jadwal ini SETELAH disimpan
     */
    public function assertStudents(ScheduleRoutine $routine, array $studentIds): void
    {
        $branchId = (string) $routine->branch_id;

        if (($max = $this->limit($branchId, 'schedules', 'students')) === null) {
            return;
        }

        $this->lockBranch($branchId);
        $before = $this->activeStudentIds($branchId);
        $after = $this->activeStudentIds($branchId, $routine->exists ? (string) $routine->getKey() : null);

        $counts = $routine->isActive() && (! $routine->ends_on || ! $routine->ends_on->lt(today()));
        if ($counts && $studentIds) {
            // Hanya murid milik branch ini yang dihitung (id dari branch lain / murid yang sudah keluar diabaikan).
            $today = today()->toDateString();
            $after = $after->merge(DB::table('schedule_students')->where('branch_id', $branchId)->whereIn('id', array_values($studentIds))
                ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $today))->pluck('id'))->unique();
        }

        $used = $before->count();
        $total = $after->count();

        if ($total <= $max || $total <= $used) {
            return;
        }

        $intro = $used >= $max
            ? "Kuota murid aktif paket Anda sudah penuh: {$this->number($used)} murid sedang ikut jadwal, batas paketnya {$this->number($max)} murid."
            : 'Sisa kuota murid aktif tinggal '.$this->number($max - $used).", sedangkan jadwal ini menambah {$this->number($total - $used)} murid baru.";

        throw (new PlanLimitException($intro.' Keluarkan dulu murid yang sudah berhenti les dari jadwalnya, atau tambah kuota murid lewat Paket Saya.'))
            ->offer('Tambah kuota murid', 'packages.catalog');
    }

    /** Ringkasan pemakaian untuk owner, per layanan, mis. ['invoices' => ['Invoice bulan ini: 120 / 300']]. Hanya yang dibatasi. */
    public function summary(string $branchId): array
    {
        $limits = BranchSubscription::where('branch_id', $branchId)->where('ends_at', '>', now())->pluck('limits', 'app_key');
        $since = now()->startOfMonth();
        $summary = [];

        foreach ([Apps::EVENTS, Apps::PAGES] as $app) {
            if (! $pages = $limits[$app] ?? null) {
                continue;
            }

            $used = PageCategory::where('branch_id', $branchId)->whereIn('page_type_id', array_keys($pages))->selectRaw('page_type_id, COUNT(*) AS total')->groupBy('page_type_id')->pluck('total', 'page_type_id');
            $names = PageType::whereKey(array_keys($pages))->pluck('name', 'id');
            foreach ($pages as $typeId => $max) {
                $summary[$app][] = ($names[$typeId] ?? 'Page').': '.(int) ($used[$typeId] ?? 0).' / '.$max;
            }
        }

        if (isset($limits['invoices']['monthly'])) {
            $used = Invoice::where('branch_id', $branchId)->where('created_at', '>=', $since)->count();
            $summary['invoices'][] = 'Invoice bulan ini: '.$this->number($used).' / '.$this->number($limits['invoices']['monthly']);
        }

        if (isset($limits['schedules']['students'])) {
            $used = $this->activeStudentIds($branchId)->count();
            $summary['schedules'][] = 'Murid aktif: '.$this->number($used).' / '.$this->number($limits['schedules']['students']);
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
