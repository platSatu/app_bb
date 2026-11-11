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
 * Bulanan: jadwal rutin mingguan untuk satu bulan (starts_on = tgl 1, ends_on = akhir bulan); bulan berikutnya lewat Duplikat.
 * Paket (pattern terisi, grade paket): privat, mulai starts_on mengikuti pola, ends_on = tanggal sesi terakhir.
 * Sesi per tanggal dibuat otomatis, maksimal sesuai pertemuan per bulan di grade. Peserta yang keluar tidak dihapus,
 * hanya diberi left_on, supaya arsip bulanan tetap utuh.
 */
class ScheduleRoutine extends Model
{
    use BelongsToScheduleBranch, HasActiveStatus, HasUuids;

    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public const LABEL_WITH = ['grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name'];

    public const PATTERN_LABELS = ['weekly' => 'Tiap minggu', 'interval' => 'Tiap beberapa hari', 'manual' => 'Pilih tanggal sendiri'];

    protected $fillable = ['grade_id', 'teacher_id', 'room_id', 'day_of_week', 'pattern', 'start_time', 'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'notes', 'status'];

    protected function casts(): array
    {
        return ['day_of_week' => 'integer', 'duration_minutes' => 'integer', 'capacity' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'pattern' => 'array'];
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

    /** Jadwal paket (punya pola pertemuan), bukan jadwal bulanan. */
    public function isPackage(): bool
    {
        return ! empty($this->pattern);
    }

    /** Pola paket yang mudah dibaca, mis. "Tiap Selasa & Kamis", "Tiap 3 hari", "Tanggal pilihan". */
    public function patternLabel(): string
    {
        return match ($this->pattern['type'] ?? null) {
            'weekly' => 'Tiap '.collect($this->pattern['days'] ?? [])->sort()->map(fn ($d) => self::DAYS[$d] ?? '')->filter()->join(', ', ' & '),
            'interval' => 'Tiap '.($this->pattern['every'] ?? 1).' hari',
            'manual' => 'Tanggal pilihan',
            default => $this->dayLabel(),
        };
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
        return $this->grade?->label().' · '.($this->isPackage() ? $this->patternLabel() : $this->dayLabel()).' '.$this->timeRange();
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
