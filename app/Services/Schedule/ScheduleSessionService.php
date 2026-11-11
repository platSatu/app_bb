<?php

namespace App\Services\Schedule;

use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Absensi & perubahan satu sesi. Honor dicatat per murid saat sesi diselesaikan:
 * hadir/alpa = harga per pertemuan dibagi pengajar & owner; izin/batal = 0.
 * Dipakai juga nanti oleh dashboard pengajar ($actor bisa null).
 */
class ScheduleSessionService
{
    public function __construct(private readonly ScheduleConflict $conflict)
    {
    }

    /** @param array<string, string> $statuses student_id => status */
    public function complete(ScheduleSession $session, array $statuses, ?array $notes, ?User $actor): void
    {
        DB::transaction(function () use ($session, $statuses, $notes, $actor) {
            $locked = ScheduleSession::with(['grade', 'attendances'])->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ScheduleSession::STATUS_CANCELLED) {
                throw new RuntimeException('Sesi ini sudah dibatalkan, absensinya tidak bisa diisi.');
            }

            if ($locked->start_at->isFuture()) {
                throw new RuntimeException('Absensi baru bisa diisi setelah sesi dimulai ('.$locked->start_at->translatedFormat('d M Y H:i').').');
            }

            $allowed = [ScheduleAttendance::STATUS_PRESENT, ScheduleAttendance::STATUS_ABSENT, ScheduleAttendance::STATUS_EXCUSED];

            foreach ($locked->attendances as $attendance) {
                $status = $statuses[$attendance->student_id] ?? null;

                if (! in_array($status, $allowed, true)) {
                    throw new RuntimeException('Isi status kehadiran semua murid dulu ya (Hadir, Alpa, atau Izin).');
                }

                if ($attendance->makeup_session_id && $status !== ScheduleAttendance::STATUS_EXCUSED) {
                    throw new RuntimeException('Salah satu murid sudah dibuatkan sesi pengganti, jadi statusnya harus tetap Izin.');
                }

                $billable = in_array($status, ScheduleAttendance::BILLABLE, true);
                $attendance->forceFill([
                    'status' => $status,
                    'teacher_fee' => $billable ? $locked->grade->teacherShare() : 0,
                    'owner_fee' => $billable ? $locked->grade->ownerShare() : 0,
                    'notes' => isset($notes[$attendance->student_id]) ? mb_substr(trim((string) $notes[$attendance->student_id]), 0, 255) ?: null : $attendance->notes,
                ])->save();
            }

            $locked->forceFill(['status' => ScheduleSession::STATUS_DONE, 'completed_at' => now(), 'completed_by' => $actor?->getKey()])->save();
        });
    }

    /** Pengajar bisa absen (scan QR tempat) mulai sekian menit sebelum sesi dimulai sampai sesi berakhir. */
    public const CHECK_IN_EARLY_MINUTES = 60;

    /** Sesi pengajar hari ini yang tampil di halaman Scan Absen: belum berakhir, atau sudah absen tapi belum diselesaikan. */
    public static function scanToday(array $teacherIds): Builder
    {
        return ScheduleSession::whereIn('teacher_id', $teacherIds)
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->whereBetween('start_at', [today(), today()->endOfDay()])
            ->where(fn ($q) => $q->where('end_at', '>=', now())->orWhereNotNull('teacher_checked_in_at'));
    }

    /**
     * Absen pengajar: scan QR tempat les. Semua sesinya di branch itu yang sedang berlangsung / dimulai dalam
     * CHECK_IN_EARLY_MINUTES menit ditandai hadir dengan jam scan. Setelah itu murid di sesi tersebut bisa discan.
     *
     * @return Collection<int, ScheduleSession>
     */
    public function teacherCheckIn(string $branchId, array $teacherIds): Collection
    {
        return DB::transaction(function () use ($branchId, $teacherIds) {
            $window = ScheduleSession::whereIn('teacher_id', $teacherIds)->where('branch_id', $branchId)
                ->where('status', ScheduleSession::STATUS_SCHEDULED)
                ->where('start_at', '<=', now()->addMinutes(self::CHECK_IN_EARLY_MINUTES))
                ->where('end_at', '>=', now())
                ->orderBy('start_at')->lockForUpdate()->get();

            $open = $window->whereNull('teacher_checked_in_at');

            if ($open->isEmpty()) {
                $done = $window->first();
                throw new RuntimeException($done
                    ? 'Anda sudah absen pukul '.$done->teacher_checked_in_at->format('H:i').'. Silakan lanjut scan kartu murid.'
                    : 'Belum ada sesi Anda di tempat ini. Absen bisa dilakukan mulai '.self::CHECK_IN_EARLY_MINUTES.' menit sebelum sesi dimulai.');
            }

            $open->each(fn (ScheduleSession $session) => $session->forceFill(['teacher_checked_in_at' => now()])->save());

            return $open->values();
        });
    }

    /**
     * Absen murid lewat scan kartu QR oleh pengajar sesi itu sendiri, setelah pengajarnya absen.
     * Hanya sesi hari ini; satu murid hanya bisa discan sekali per sesi. Jam scan & pemindainya dicatat.
     * Kode 403 = murid tidak ada di sesi pengajar ini hari ini (dicatat di log oleh pemanggil).
     */
    public function checkIn(string $studentId, array $teacherIds, User $actor): ScheduleAttendance
    {
        return DB::transaction(function () use ($studentId, $teacherIds, $actor) {
            $today = ScheduleSession::whereIn('teacher_id', $teacherIds)->where('status', ScheduleSession::STATUS_SCHEDULED)
                ->whereBetween('start_at', [today(), today()->endOfDay()]);

            $candidates = ScheduleAttendance::with(['student:id,name', 'session:id,start_at,teacher_checked_in_at'])
                ->where('student_id', $studentId)->whereIn('session_id', $today->select('id'))
                ->lockForUpdate()->get()->sortBy('session.start_at');

            if ($candidates->isEmpty()) {
                throw new RuntimeException('Murid ini tidak terdaftar di sesi Anda hari ini.', 403);
            }

            $name = $candidates->first()->student?->name ?? 'Murid ini';
            $ready = $candidates->filter(fn ($a) => $a->session?->teacher_checked_in_at);

            if ($ready->isEmpty()) {
                throw new RuntimeException("Absen Anda dulu ya: scan QR tempat les, baru scan kartu {$name}.");
            }

            $attendance = $ready->first(fn ($a) => ! $a->scanned_at);

            if (! $attendance) {
                throw new RuntimeException("{$name} sudah absen pukul {$ready->last()->scanned_at->format('H:i')}.");
            }

            if ($attendance->makeup_session_id) {
                throw new RuntimeException("{$name} sudah izin dan dibuatkan sesi pengganti, jadi tidak perlu discan.");
            }

            $attendance->forceFill(['status' => ScheduleAttendance::STATUS_PRESENT, 'scanned_at' => now(), 'scanned_by' => $actor->getKey()])->save();

            return $attendance;
        });
    }

    public function cancel(ScheduleSession $session, string $reason): void
    {
        DB::transaction(function () use ($session, $reason) {
            $locked = ScheduleSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ScheduleSession::STATUS_SCHEDULED) {
                throw new RuntimeException('Hanya sesi yang masih terjadwal yang bisa dibatalkan.');
            }

            $locked->forceFill(['status' => ScheduleSession::STATUS_CANCELLED, 'cancel_reason' => $reason])->save();
        });

        // Paket: pertemuan yang dibatalkan langsung diganti di tanggal pola berikutnya, jatah paket tetap utuh.
        if ($session->routine?->isPackage()) {
            app(ScheduleRoutineService::class)->extend($session->routine);
        }
    }

    /** Pindah tanggal/jam/pengajar/ruangan satu sesi saja. Jatah bulannya tetap (period tidak berubah). */
    public function move(ScheduleSession $session, string $date, string $time, string $teacherId, string $roomId): void
    {
        DB::transaction(function () use ($session, $date, $time, $teacherId, $roomId) {
            UserBranch::whereKey($session->branch_id)->lockForUpdate()->first();
            $locked = ScheduleSession::with('attendances')->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ScheduleSession::STATUS_SCHEDULED) {
                throw new RuntimeException('Hanya sesi yang masih terjadwal yang bisa dipindah.');
            }

            [$start, $end] = $this->slot($date, $time, (int) $locked->start_at->diffInMinutes($locked->end_at));
            $this->ensureFree($locked->branch_id, $start, $end, $teacherId, $roomId, $locked->grade_id, $locked->attendances->pluck('student_id')->all(), $locked->getKey());

            if ($locked->routine_id && ScheduleSession::where('routine_id', $locked->routine_id)->whereDate('date', $start)->whereKeyNot($locked->getKey())->exists()) {
                throw new RuntimeException('Jadwal ini sudah punya sesi lain di tanggal tersebut. Pilih tanggal lain.');
            }

            $locked->forceFill([
                'date' => $start->toDateString(),
                'start_at' => $start,
                'end_at' => $end,
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
                'moved_at' => now(),
                // Ganti pengajar: pengajar baru harus absen sendiri.
                'teacher_checked_in_at' => $teacherId === $locked->teacher_id ? $locked->teacher_checked_in_at : null,
            ])->save();
        });
    }

    /** Sesi pengganti untuk murid yang izin. Honor dihitung di sesi pengganti saat diselesaikan. */
    public function makeup(ScheduleAttendance $attendance, string $date, string $time, string $teacherId, string $roomId): ScheduleSession
    {
        return DB::transaction(function () use ($attendance, $date, $time, $teacherId, $roomId) {
            $original = $attendance->session;
            UserBranch::whereKey($original->branch_id)->lockForUpdate()->first();
            $locked = ScheduleAttendance::whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ScheduleAttendance::STATUS_EXCUSED || $original->status !== ScheduleSession::STATUS_DONE) {
                throw new RuntimeException('Sesi pengganti hanya untuk murid yang Izin di sesi yang sudah diselesaikan.');
            }

            if ($locked->makeup_session_id) {
                throw new RuntimeException('Murid ini sudah dibuatkan sesi pengganti.');
            }

            [$start, $end] = $this->slot($date, $time, (int) $original->start_at->diffInMinutes($original->end_at));
            $this->ensureFree($original->branch_id, $start, $end, $teacherId, $roomId, $original->grade_id, [$locked->student_id]);

            $session = new ScheduleSession();
            $session->forceFill([
                'branch_id' => $original->branch_id,
                'grade_id' => $original->grade_id,
                'teacher_id' => $teacherId,
                'room_id' => $roomId,
                'date' => $start->toDateString(),
                'start_at' => $start,
                'end_at' => $end,
                'status' => ScheduleSession::STATUS_SCHEDULED,
                'notes' => 'Pengganti sesi '.$original->start_at->translatedFormat('d M Y H:i'),
            ])->save();

            $replacement = new ScheduleAttendance();
            $replacement->forceFill([
                'session_id' => $session->getKey(),
                'student_id' => $locked->student_id,
                'status' => ScheduleAttendance::STATUS_PENDING,
                'replaces_attendance_id' => $locked->getKey(),
            ])->save();

            $locked->forceFill(['makeup_session_id' => $session->getKey()])->save();

            return $session;
        });
    }

    private function slot(string $date, string $time, int $minutes): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.substr($time, 0, 5));

        if ($start->isPast()) {
            throw new RuntimeException('Pilih tanggal dan jam yang belum lewat ya.');
        }

        return [$start, $start->copy()->addMinutes(max($minutes, 15))];
    }

    private function ensureFree(string $branchId, Carbon $start, Carbon $end, string $teacherId, string $roomId, string $gradeId, array $studentIds, ?string $ignoreId = null): void
    {
        $teaches = ScheduleTeacher::whereKey($teacherId)->where('branch_id', $branchId)->active()
            ->whereHas('grades', fn ($q) => $q->whereKey($gradeId))->exists();

        if (! $teaches) {
            throw new RuntimeException('Pengajar yang dipilih tidak aktif atau belum ditandai mengajar grade ini (atur di menu Pengajar).');
        }

        if ($message = $this->conflict->forSession($branchId, $start, $end, $teacherId, $roomId, $studentIds, $ignoreId)) {
            throw new RuntimeException($message);
        }
    }
}
