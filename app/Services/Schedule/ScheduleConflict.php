<?php

namespace App\Services\Schedule;

use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use Carbon\CarbonInterface;

/** Pengajar, ruangan, dan murid tidak boleh dipakai di jam yang bertabrakan. Hasilnya pesan yang bisa langsung ditampilkan. */
class ScheduleConflict
{
    public function forRoutine(ScheduleRoutine $routine, array $studentIds): ?string
    {
        $other = ScheduleRoutine::query()
            ->with(['teacher:id,name', 'room:id,name', 'students:id,name', ...ScheduleRoutine::LABEL_WITH])
            ->where('branch_id', $routine->branch_id)
            ->where('status', ScheduleRoutine::STATUS_ACTIVE)
            ->when($routine->exists, fn ($q) => $q->whereKeyNot($routine->getKey()))
            ->where('day_of_week', $routine->day_of_week)
            ->where('start_time', '<', $routine->end_time)
            ->where('end_time', '>', $routine->start_time)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $routine->starts_on))
            ->when($routine->ends_on, fn ($q) => $q->where('starts_on', '<=', $routine->ends_on))
            ->where(fn ($q) => $q->where('teacher_id', $routine->teacher_id)
                ->orWhere('room_id', $routine->room_id)
                ->orWhereHas('students', fn ($s) => $s->whereIn('schedule_students.id', $studentIds ?: ['-'])))
            ->first();

        return $other ? $this->describe($other->teacher_id === $routine->teacher_id, $other->room_id === $routine->room_id,
            $other->students->whereIn('id', $studentIds)->pluck('name'), $other->teacher?->name, $other->room?->name,
            $other->dayLabel().' '.$other->timeRange(), $other->grade?->label()) : null;
    }

    public function forSession(string $branchId, CarbonInterface $start, CarbonInterface $end, string $teacherId, string $roomId, array $studentIds, ?string $ignoreId = null): ?string
    {
        $other = ScheduleSession::query()
            ->with(['teacher:id,name', 'room:id,name', 'attendances.student:id,name', 'grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name'])
            ->where('branch_id', $branchId)
            ->where('status', '!=', ScheduleSession::STATUS_CANCELLED)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->where(fn ($q) => $q->where('teacher_id', $teacherId)
                ->orWhere('room_id', $roomId)
                ->orWhereHas('attendances', fn ($a) => $a->whereIn('student_id', $studentIds ?: ['-'])))
            ->first();

        return $other ? $this->describe($other->teacher_id === $teacherId, $other->room_id === $roomId,
            $other->attendances->whereIn('student_id', $studentIds)->pluck('student.name'), $other->teacher?->name, $other->room?->name,
            $other->start_at->translatedFormat('l, d M Y').' '.$other->timeRange(), $other->grade?->label()) : null;
    }

    private function describe(bool $teacher, bool $room, $students, ?string $teacherName, ?string $roomName, string $when, ?string $grade): string
    {
        $who = match (true) {
            $teacher => "Pengajar {$teacherName} sudah mengajar",
            $room => "Ruangan {$roomName} sudah dipakai",
            default => 'Murid '.$students->join(', ', ' dan ').' sudah punya jadwal',
        };

        return "{$who} di {$when} ({$grade}). Silakan pilih jam, hari, atau ".($teacher ? 'pengajar' : ($room ? 'ruangan' : 'murid')).' lain.';
    }
}
