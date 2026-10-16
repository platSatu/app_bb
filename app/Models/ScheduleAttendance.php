<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleAttendance extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_EXCUSED = 'excused';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Belum diisi',
        self::STATUS_PRESENT => 'Hadir',
        self::STATUS_ABSENT => 'Alpa',
        self::STATUS_EXCUSED => 'Izin',
    ];

    /** Hadir & alpa tetap dihitung honor; izin tidak (bisa dibuatkan sesi pengganti). */
    public const BILLABLE = [self::STATUS_PRESENT, self::STATUS_ABSENT];

    private const BADGES = [
        self::STATUS_PENDING => 'bg-light text-dark',
        self::STATUS_PRESENT => 'bg-success-subtle text-success',
        self::STATUS_ABSENT => 'bg-danger-subtle text-danger',
        self::STATUS_EXCUSED => 'bg-warning-subtle text-warning',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['teacher_fee' => 'integer', 'owner_fee' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ScheduleStudent::class, 'student_id');
    }

    public function makeupSession(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class, 'makeup_session_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_attendance_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::BADGES[$this->status] ?? 'bg-light text-dark';
    }
}
