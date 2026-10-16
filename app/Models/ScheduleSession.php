<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleSession extends Model
{
    use BelongsToScheduleBranch, HasUuids;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Terjadwal',
        self::STATUS_DONE => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    private const BADGES = [
        self::STATUS_SCHEDULED => 'bg-info-subtle text-info',
        self::STATUS_DONE => 'bg-success-subtle text-success',
        self::STATUS_CANCELLED => 'bg-secondary-subtle text-secondary',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'start_at' => 'datetime', 'end_at' => 'datetime', 'moved_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(ScheduleRoutine::class, 'routine_id');
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

    public function attendances(): HasMany
    {
        return $this->hasMany(ScheduleAttendance::class, 'session_id');
    }

    public function isMakeup(): bool
    {
        return $this->routine_id === null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::BADGES[$this->status] ?? 'bg-light text-dark';
    }

    public function timeRange(): string
    {
        return $this->start_at->format('H:i').'–'.$this->end_at->format('H:i');
    }
}
