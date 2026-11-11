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
            $removed = $this->syncParticipants($routine, $studentIds);
            $this->refresh($routine, $removed);

            return $routine;
        });
    }

    /**
     * Salin jadwal ke bulan berikutnya: grade, pengajar, ruangan, hari, jam, kapasitas & murid yang masih aktif.
     * Tidak dobel: kalau bulan itu sudah punya jadwal yang sama (grade, pengajar, hari, jam), dilewati.
     */
    public function duplicate(ScheduleRoutine $routine): ScheduleRoutine
    {
        $start = $routine->starts_on->copy()->addMonthNoOverflow()->startOfMonth();

        $exists = ScheduleRoutine::where('branch_id', $routine->branch_id)->where('grade_id', $routine->grade_id)
            ->where('teacher_id', $routine->teacher_id)->where('day_of_week', $routine->day_of_week)
            ->where('start_time', $routine->start_time)->whereDate('starts_on', $start)->exists();

        if ($exists) {
            throw new RuntimeException('Jadwal '.$routine->label().' untuk '.$start->translatedFormat('F Y').' sudah ada.');
        }

        $copy = new ScheduleRoutine();
        $copy->forceFill(['branch_id' => $routine->branch_id]);

        return $this->save($copy, [
            ...$routine->only(['grade_id', 'teacher_id', 'room_id', 'day_of_week', 'duration_minutes', 'capacity', 'notes']),
            'start_time' => substr((string) $routine->start_time, 0, 5),
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->endOfMonth()->toDateString(),
            'status' => ScheduleRoutine::STATUS_ACTIVE,
        ], $routine->students()->where('schedule_students.status', 'active')->pluck('schedule_students.id')->all());
    }

    /**
     * Murid resign/keluar: dikeluarkan dari semua jadwal per tanggal itu (left_on) dan dihapus dari sesi mendatang
     * yang belum diabsen. Absensi & honor sebelumnya tetap utuh sebagai arsip.
     */
    public function removeStudent(string $studentId, \Carbon\CarbonInterface $leftOn): void
    {
        DB::table('schedule_participants')->where('student_id', $studentId)->whereNull('left_on')
            ->update(['left_on' => $leftOn->toDateString(), 'updated_at' => now()]);

        ScheduleAttendance::where('student_id', $studentId)->where('status', ScheduleAttendance::STATUS_PENDING)
            ->whereHas('session', fn ($q) => $q->where('status', ScheduleSession::STATUS_SCHEDULED)->whereDate('date', '>=', $leftOn))
            ->delete();
    }

    /**
     * Peserta dicatat masuk/keluar per tanggal, tidak dihapus: murid baru joined_on = tanggal mulai ikut, murid yang
     * dikeluarkan diberi left_on. Mengembalikan id murid yang baru keluar.
     */
    private function syncParticipants(ScheduleRoutine $routine, array $studentIds): array
    {
        $date = max(today(), $routine->starts_on->copy()->startOfDay())->toDateString();
        $table = DB::table('schedule_participants')->where('routine_id', $routine->getKey());
        $rows = (clone $table)->get()->keyBy('student_id');

        foreach ($studentIds as $id) {
            $row = $rows->get($id);

            if (! $row) {
                DB::table('schedule_participants')->insert(['routine_id' => $routine->getKey(), 'student_id' => $id, 'joined_on' => $date, 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($row->left_on) {
                (clone $table)->where('student_id', $id)->update(['joined_on' => $date, 'left_on' => null, 'updated_at' => now()]);
            }
        }

        $removed = $rows->filter(fn ($row) => ! $row->left_on && ! in_array($row->student_id, $studentIds, true))->keys()->all();
        (clone $table)->whereIn('student_id', $removed ?: ['-'])->update(['left_on' => $date, 'updated_at' => now()]);

        return $removed;
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

    /**
     * Sesi mendatang yang belum disentuh dibuat ulang mengikuti jadwal terbaru (jam, pengajar, ruangan, murid).
     * Sesi mendatang yang sudah sebagian diabsen/dipindah tetap ada: murid baru ditambahkan, murid yang keluar dilepas.
     */
    private function refresh(ScheduleRoutine $routine, array $removed = []): void
    {
        $upcoming = fn () => $routine->sessions()->where('status', ScheduleSession::STATUS_SCHEDULED)->where('start_at', '>', now());

        $upcoming()->whereNull('moved_at')
            ->whereDoesntHave('attendances', fn ($q) => $q->where('status', '!=', ScheduleAttendance::STATUS_PENDING))
            ->delete();

        $active = $routine->students()->pluck('schedule_students.id')->all();

        foreach ($upcoming()->with('attendances:id,session_id,student_id')->get() as $session) {
            $session->attendances()->whereIn('student_id', $removed ?: ['-'])->where('status', ScheduleAttendance::STATUS_PENDING)->delete();

            foreach (array_diff($active, $session->attendances->pluck('student_id')->all()) as $studentId) {
                (new ScheduleAttendance())->forceFill(['session_id' => $session->getKey(), 'student_id' => $studentId, 'status' => ScheduleAttendance::STATUS_PENDING])->save();
            }
        }

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
