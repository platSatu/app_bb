<?php

namespace App\Services\Schedule;

use App\Models\Invoice;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use App\Services\Invoice\InvoiceService;
use App\Services\Subscription\AppAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menu Murid › Buat Tagihan: invoice les per murid per kelas, langsung masuk ke menu Invoice tanpa input manual.
 *  - Kategori invoice = nama Bidang, periode = "Kategori · Grade · Bulan" (paket: "… · Paket 15x · mulai tgl"), nominal periode = harga grade.
 *  - Bulanan: dibayar di muka sesuai sesi terjadwal bulan itu (masuk di tengah bulan = prorata sisa sesi), jatuh tempo tgl 10.
 *  - Paket: harga penuh sekali, di bulan paket dimulai, jatuh tempo tanggal mulai.
 * Tagihan yang sudah pernah dibuat dilewati, jadi aman diklik ulang.
 */
class ScheduleBillingService
{
    public const DUE_DAY = 10;

    public function __construct(private readonly InvoiceService $invoices, private readonly CustomerService $customers, private readonly AppAccess $access)
    {
    }

    /** Penanda tagihan (invoice external_id): bulanan per murid per grade per bulan, paket per murid per jadwal. */
    public static function key(string $studentId, ScheduleRoutine $routine, Carbon $month): string
    {
        return $routine->isPackage()
            ? 'jadwal:'.$studentId.':paket:'.$routine->getKey()
            : 'jadwal:'.$studentId.':'.$routine->grade_id.':'.$month->format('Y-m');
    }

    /** @return array{created: int, notes: list<string>} */
    public function bill(Collection $students, Carbon $month, User $actor): array
    {
        $month = $month->copy()->startOfMonth();
        $created = 0;
        $notes = [];

        foreach ($students as $student) {
            if (! $this->access->branchActive($student->branch_id, 'invoices')) {
                $notes[] = "{$student->name}: paket Invoice branch ini belum aktif.";
                continue;
            }

            if (! $student->phone && ! $student->email) {
                $notes[] = "{$student->name}: belum punya WhatsApp atau email, tagihan tidak bisa dikirim.";
                continue;
            }

            $lines = $this->lines($student, $month);

            if ($lines === []) {
                $notes[] = "{$student->name}: tidak ada kelas yang perlu ditagih di ".$month->translatedFormat('F Y').'.';
                continue;
            }

            $branch = UserBranch::findOrFail($student->branch_id);
            $customer = DB::transaction(function () use ($branch, $student) {
                UserBranch::whereKey($branch->getKey())->lockForUpdate()->first();

                return $this->customers->resolve($branch, 'invoices', $student->guardian_name ?: $student->name, $student->phone, $student->email, 'schedule');
            });

            foreach ($lines as $line) {
                try {
                    $invoice = $this->invoices->issueInPeriod($branch, $line['period'], $customer, $line['amount'],
                        $line['period']['name'].' · '.$student->name, $line['key'], Invoice::SOURCE_SCHEDULE, $actor);
                } catch (RuntimeException $e) {
                    $notes[] = $e->getMessage(); // mis. kuota invoice bulan ini habis

                    return ['created' => $created, 'notes' => $notes];
                }

                if ($invoice) {
                    $created++;
                } else {
                    $notes[] = "{$student->name} · {$line['period']['name']}: sudah pernah ditagih.";
                }
            }
        }

        return ['created' => $created, 'notes' => $notes];
    }

    /** Baris tagihan murid di bulan ini: satu per grade bulanan + satu per paket yang mulai bulan ini. */
    private function lines(ScheduleStudent $student, Carbon $month): array
    {
        $end = $month->copy()->endOfMonth();
        $routines = ScheduleRoutine::with(['grade', 'grade.category.subject'])
            ->whereHas('participants', fn ($q) => $q->whereKey($student->getKey())
                ->where(fn ($w) => $w->whereNull('schedule_participants.left_on')->orWhere('schedule_participants.left_on', '>=', $month->toDateString())))
            ->whereDate('starts_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month))
            ->get();
        $lines = [];

        foreach ($routines->filter->isPackage()->filter(fn ($r) => $r->starts_on->isSameMonth($month)) as $routine) {
            $lines[] = [
                'key' => self::key($student->getKey(), $routine, $month),
                'amount' => (int) $routine->grade->price,
                'period' => $this->period($routine, 'Paket '.$routine->grade->meetings_per_month.'x · mulai '.$routine->starts_on->translatedFormat('d M Y'), max($routine->starts_on, today())),
            ];
        }

        // Bulanan, per grade: harga ÷ pertemuan × sesi murid ini bulan itu (yang tidak dibatalkan), maksimal harga sebulan.
        foreach ($routines->reject->isPackage()->groupBy('grade_id') as $group) {
            $grade = $group->first()->grade;
            $sessions = ScheduleSession::whereIn('routine_id', $group->modelKeys())->where('period', $month->format('Y-m'))
                ->where('status', '!=', ScheduleSession::STATUS_CANCELLED)
                ->whereHas('attendances', fn ($q) => $q->where('student_id', $student->getKey()))->count();

            if ($sessions > 0 && $grade->price > 0) {
                $lines[] = [
                    'key' => self::key($student->getKey(), $group->first(), $month),
                    'amount' => (int) min($grade->price, round($grade->price / max(1, $grade->meetings_per_month) * $sessions)),
                    'period' => $this->period($group->first(), $month->translatedFormat('F Y'), max($month->copy()->day(self::DUE_DAY), today())),
                ];
            }
        }

        return $lines;
    }

    /** Nama disalin persis dari Schedule: kategori invoice = Bidang, periode = Kategori · Grade · $suffix. */
    private function period(ScheduleRoutine $routine, string $suffix, Carbon $due): array
    {
        $grade = $routine->grade;

        return [
            'category' => $grade->category?->subject?->name ?? $grade->name,
            'name' => collect([$grade->category?->name, $grade->name, $suffix])->filter()->join(' · '),
            'amount' => (int) $grade->price,
            'due_date' => $due->toDateString(),
        ];
    }
}
