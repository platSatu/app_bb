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
 * Jadwal rutin -> sesi per tanggal.
 *  - Bulanan: jatah per bulan = pertemuan/bulan di grade (mis. 4x), dihitung per "period" bulan sehingga
 *    pertemuan ke-5 tidak dibuat dan sesi yang dipindah tetap terhitung.
 *  - Paket: tepat sejumlah pertemuan paket (mis. 15x) mengikuti pola (tiap minggu / tiap N hari / tanggal pilihan),
 *    langsung dibuat semua. Sesi yang dibatalkan diganti di tanggal pola berikutnya, jadi jatah paket tetap utuh.
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

            if ($routine->isPackage() && count($studentIds) > 1) {
                throw new RuntimeException('Kelas paket untuk 1 murid (privat). Pilih satu murid saja; murid lain dibuatkan jadwal paket sendiri.');
            }

            if (count($studentIds) > $routine->capacity) {
                throw new RuntimeException("Murid yang dipilih (".count($studentIds).") melebihi kapasitas {$routine->capacity}. Tambah kapasitas untuk kelas grup, atau kurangi murid.");
            }

            // Paket dicek bentrok per tanggal saat sesinya dibuat (polanya tidak selalu mingguan).
            if ($routine->isActive() && ! $routine->isPackage() && $message = $this->conflict->forRoutine($routine, $studentIds)) {
                throw new RuntimeException($message);
            }

            $routine->save();
            $removed = $this->syncParticipants($routine, $studentIds);
            $this->refresh($routine, $removed, strict: true);

            return $routine;
        });
    }

    /**
     * Salin jadwal ke bulan berikutnya: grade, pengajar, ruangan, hari, jam, kapasitas & murid yang masih aktif.
     * Tidak dobel: kalau bulan itu sudah punya jadwal yang sama (grade, pengajar, hari, jam), dilewati.
     */
    public function duplicate(ScheduleRoutine $routine): ScheduleRoutine
    {
        if ($routine->isPackage()) {
            throw new RuntimeException('Jadwal paket '.$routine->label().' tidak diduplikat; buat jadwal paket baru kalau murid mengambil paket lagi.');
        }

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
    private function refresh(ScheduleRoutine $routine, array $removed = [], bool $strict = false): void
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

        $this->generate($routine, $strict);
    }

    /** $strict: tanggal paket yang bentrok ditolak dengan pesan (saat admin menyimpan); dari scheduler dilewati saja. */
    private function generate(ScheduleRoutine $routine, bool $strict = false): int
    {
        if (! $routine->isActive()) {
            return 0;
        }

        if ($routine->isPackage()) {
            return $this->generatePackage($routine, $strict);
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

    /**
     * Paket: buat sesi sampai jumlahnya (di luar yang dibatalkan) = pertemuan paket, mengikuti pola mulai starts_on.
     * Tanggal yang sudah lewat / sudah punya sesi dilewati. ends_on diisi tanggal sesi terakhir supaya filter bulan & rekap tepat.
     */
    private function generatePackage(ScheduleRoutine $routine, bool $strict): int
    {
        $routine->loadMissing(['grade', 'students:id']);
        $studentIds = $routine->students->pluck('id')->all();
        $have = $routine->sessions()->where('status', '!=', ScheduleSession::STATUS_CANCELLED)->count();
        $taken = $routine->sessions()->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->all();
        $created = 0;

        foreach ($this->packageDates($routine) as $day) {
            if ($have >= $routine->grade->meetings_per_month) {
                break;
            }

            $start = $day->copy()->setTimeFromTimeString($routine->start_time);

            if ($start->lte(now()) || in_array($day->toDateString(), $taken, true)) {
                continue;
            }

            $end = $start->copy()->addMinutes($routine->duration_minutes);

            if ($message = $this->conflict->forSession($routine->branch_id, $start, $end, $routine->teacher_id, $routine->room_id, $studentIds)) {
                if ($strict) {
                    throw new RuntimeException('Pertemuan '.$start->translatedFormat('l, d M Y H:i').' bentrok. '.$message);
                }

                continue;
            }

            $this->createSession($routine, $start->format('Y-m'), $start, $end, $studentIds);
            $have++;
            $created++;
        }

        if ($strict && $have < $routine->grade->meetings_per_month) {
            throw new RuntimeException(($routine->pattern['type'] ?? null) === 'manual'
                ? 'Tanggal yang dipilih baru '.$have.' dari '.$routine->grade->meetings_per_month.' pertemuan. Tambahkan tanggalnya (tanggal yang sudah lewat tidak dihitung).'
                : 'Pola ini baru menghasilkan '.$have.' dari '.$routine->grade->meetings_per_month.' pertemuan dalam 2 tahun. Majukan tanggal mulai, tambah hari, atau perkecil jarak harinya.');
        }

        $last = $routine->sessions()->where('status', '!=', ScheduleSession::STATUS_CANCELLED)->max('date');
        $routine->forceFill(['ends_on' => $last])->saveQuietly();

        return $created;
    }

    /** Tanggal calon pertemuan paket menurut polanya, mulai starts_on (maksimal 2 tahun ke depan). */
    private function packageDates(ScheduleRoutine $routine): \Generator
    {
        $pattern = $routine->pattern;

        if (($pattern['type'] ?? null) === 'manual') {
            foreach (collect($pattern['dates'] ?? [])->sort()->values() as $date) {
                yield Carbon::parse($date)->startOfDay();
            }

            return;
        }

        $step = ($pattern['type'] ?? null) === 'interval' ? max(1, (int) ($pattern['every'] ?? 1)) : 1;
        $days = array_map('intval', $pattern['days'] ?? []);

        for ($day = $routine->starts_on->copy()->startOfDay(), $limit = $day->copy()->addYears(2); $day->lte($limit); $day->addDays($step)) {
            if ($step > 1 || in_array($day->dayOfWeekIso, $days, true)) {
                yield $day->copy();
            }
        }
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
