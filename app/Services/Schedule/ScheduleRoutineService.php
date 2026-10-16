<?php

namespace App\Services\Schedule;

use App\Models\ScheduleAttendance;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\UserBranch;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jadwal rutin -> sesi per tanggal. Jatah per bulan = pertemuan/bulan di grade (mis. 4x),
 * dihitung per "period" bulan sehingga pertemuan ke-5 tidak dibuat dan sesi yang dipindah tetap terhitung.
 * Semua perubahan dikunci per branch supaya dua admin yang menyimpan bersamaan tidak membuat jadwal bentrok.
 */
class ScheduleRoutineService
{
    public function __construct(private readonly ScheduleConflict $conflict)
    {
    }

    public function save(ScheduleRoutine $routine, array $data, array $studentIds): ScheduleRoutine
    {
        return DB::transaction(function () use ($routine, $data, $studentIds) {
            UserBranch::whereKey($routine->branch_id)->lockForUpdate()->first();

            $routine->fill($data);
            $start = substr((string) $routine->start_time, 0, 5);
            $routine->start_time = $start.':00';
            $routine->end_time = Carbon::createFromFormat('H:i', $start)->addMinutes($routine->duration_minutes)->format('H:i:s');

            if ($routine->end_time <= $routine->start_time) {
                throw new RuntimeException('Jam selesai melewati tengah malam. Majukan jam mulai atau kurangi durasinya.');
            }

            if (count($studentIds) > $routine->capacity) {
                throw new RuntimeException("Murid yang dipilih (".count($studentIds).") melebihi kapasitas {$routine->capacity}. Tambah kapasitas untuk kelas grup, atau kurangi murid.");
            }

            if ($routine->isActive() && $message = $this->conflict->forRoutine($routine, $studentIds)) {
                throw new RuntimeException($message);
            }

            $routine->save();
            $routine->students()->sync($studentIds);
            $this->refresh($routine);

            return $routine;
        });
    }

    public function delete(ScheduleRoutine $routine): void
    {
        if ($routine->sessions()->where('status', ScheduleSession::STATUS_DONE)->exists()) {
            throw new RuntimeException('Jadwal ini sudah punya sesi yang selesai (ada absensi & honor). Supaya riwayatnya aman, ubah statusnya menjadi Tidak Aktif saja.');
        }

        $routine->delete();
    }

    /** Dipanggil scheduler setiap hari: menyiapkan sesi sampai akhir bulan depan. */
    public function extend(ScheduleRoutine $routine): int
    {
        return DB::transaction(function () use ($routine) {
            UserBranch::whereKey($routine->branch_id)->lockForUpdate()->first();

            return $this->generate($routine->refresh());
        });
    }

    /** Sesi mendatang yang belum disentuh dibuat ulang mengikuti jadwal terbaru (jam, pengajar, ruangan, murid). */
    private function refresh(ScheduleRoutine $routine): void
    {
        $routine->sessions()
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->whereNull('moved_at')
            ->where('start_at', '>', now())
            ->whereDoesntHave('attendances', fn ($q) => $q->where('status', '!=', ScheduleAttendance::STATUS_PENDING))
            ->delete();

        $this->generate($routine);
    }

    private function generate(ScheduleRoutine $routine): int
    {
        if (! $routine->isActive()) {
            return 0;
        }

        $routine->loadMissing(['grade', 'students:id']);
        $from = max(today(), $routine->starts_on->copy()->startOfDay());
        $until = now()->addMonthNoOverflow()->endOfMonth();
        $until = $routine->ends_on && $routine->ends_on->lt($until) ? $routine->ends_on->copy()->endOfDay() : $until;
        $studentIds = $routine->students->pluck('id')->all();
        $created = 0;

        for ($month = $from->copy()->startOfMonth(); $month->lte($until); $month->addMonthNoOverflow()) {
            $period = $month->format('Y-m');
            $quota = $routine->grade->meetings_per_month - $routine->sessions()->where('period', $period)->count();
            $taken = $routine->sessions()->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();

            for ($day = $this->firstWeekday($month, $routine->day_of_week); $quota > 0 && $day->lte($until) && $day->month === $month->month; $day->addWeek()) {
                $start = $day->copy()->setTimeFromTimeString($routine->start_time);

                if ($day->lt($from) || $start->lte(now()) || in_array($day->toDateString(), $taken, true)) {
                    continue;
                }

                $end = $start->copy()->addMinutes($routine->duration_minutes);

                // Bentrok dengan sesi pengganti/pindahan: lewati, pakai minggu berikutnya di bulan yang sama.
                if ($this->conflict->forSession($routine->branch_id, $start, $end, $routine->teacher_id, $routine->room_id, $studentIds)) {
                    continue;
                }

                $this->createSession($routine, $period, $start, $end, $studentIds);
                $quota--;
                $created++;
            }
        }

        return $created;
    }

    private function createSession(ScheduleRoutine $routine, string $period, CarbonInterface $start, CarbonInterface $end, array $studentIds): void
    {
        $session = new ScheduleSession();
        $session->forceFill([
            'branch_id' => $routine->branch_id,
            'routine_id' => $routine->getKey(),
            'grade_id' => $routine->grade_id,
            'teacher_id' => $routine->teacher_id,
            'room_id' => $routine->room_id,
            'period' => $period,
            'date' => $start->toDateString(),
            'start_at' => $start,
            'end_at' => $end,
            'status' => ScheduleSession::STATUS_SCHEDULED,
        ])->save();

        foreach ($studentIds as $studentId) {
            $attendance = new ScheduleAttendance();
            $attendance->forceFill(['session_id' => $session->getKey(), 'student_id' => $studentId, 'status' => ScheduleAttendance::STATUS_PENDING])->save();
        }
    }

    private function firstWeekday(CarbonInterface $month, int $isoDay): Carbon
    {
        $day = Carbon::parse($month->format('Y-m-01'));

        return $day->addDays(($isoDay - $day->dayOfWeekIso + 7) % 7);
    }
}
