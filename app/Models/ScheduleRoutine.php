<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jadwal rutin mingguan untuk satu bulan (starts_on = tgl 1, ends_on = akhir bulan); bulan berikutnya dibuat lewat Duplikat.
 * Sesi per tanggal dibuat otomatis, maksimal sesuai pertemuan per bulan di grade. Peserta yang keluar tidak dihapus,
 * hanya diberi left_on, supaya arsip bulanan tetap utuh.
 */
class ScheduleRoutine extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public const LABEL_WITH = ['grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name'];

    protected $fillable = ['grade_id', 'teacher_id', 'room_id', 'day_of_week', 'start_time', 'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'notes', 'status'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'duration_minutes' => 'integer', 'capacity' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(ScheduleGrade::class, 'grade_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(ScheduleTeacher::class, 'teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ScheduleRoom::class, 'room_id');
    }

    /** Peserta aktif (belum keluar). Dipakai untuk membuat sesi, cek bentrok & form. */
    public function students(): BelongsToMany
    {
        return $this->participants()->wherePivotNull('left_on');
    }

    /** Semua peserta termasuk yang sudah keluar, dengan tanggal masuk/keluar (arsip & rekap). */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(ScheduleStudent::class, 'schedule_participants', 'routine_id', 'student_id')
            ->withPivot(['joined_on', 'left_on'])->withTimestamps();
    }

    public function monthLabel(): string
    {
        return $this->starts_on->translatedFormat('F Y');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ScheduleSession::class, 'routine_id');
    }

    public function label(): string
    {
        return $this->grade?->label().' · '.$this->dayLabel().' '.$this->timeRange();
    }

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? '-';
    }

    public function timeRange(): string
    {
        return substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5);
    }

    public function isGroup(): bool
    {
        return $this->capacity > 1;
    }
}
