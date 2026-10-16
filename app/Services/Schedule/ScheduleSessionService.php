<?php

namespace App\Services\Schedule;

use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use Carbon\Carbon;
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

    public function cancel(ScheduleSession $session, string $reason): void
    {
        DB::transaction(function () use ($session, $reason) {
            $locked = ScheduleSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ScheduleSession::STATUS_SCHEDULED) {
                throw new RuntimeException('Hanya sesi yang masih terjadwal yang bisa dibatalkan.');
            }

            $locked->forceFill(['status' => ScheduleSession::STATUS_CANCELLED, 'cancel_reason' => $reason])->save();
        });
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
