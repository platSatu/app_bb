<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan ganti hari dari murid / orang tua (lewat WhatsApp), menunggu persetujuan admin.
 *  - move   (kelas privat): saat disetujui, sesinya dipindah (sama dengan tombol Pindah di Sesi & Absensi).
 *  - makeup (kelas grup)  : saat disetujui, sesi pengganti dibuat otomatis begitu sesi asli ditutup,
 *                           karena di website sesi pengganti memang baru bisa dibuat setelah sesi selesai.
 */
class ScheduleRescheduleRequest extends Model
{
    use BelongsToScheduleBranch, HasUuids;

    public const MODE_MOVE = 'move';
    public const MODE_MAKEUP = 'makeup';

    public const STATUS_PENDING = 'pending';
    /** Disetujui, sesi pengganti menunggu sesi asli ditutup (khusus kelas grup). */
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DONE = 'done';
    public const STATUS_REJECTED = 'rejected';
    /** Disetujui tapi jadwalnya keburu terisi saat sesi pengganti mau dibuat. */
    public const STATUS_FAILED = 'failed';
    /** Batal sendiri, mis. murid ternyata hadir di sesi aslinya. */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_APPROVED => 'Disetujui, menunggu sesi selesai',
        self::STATUS_DONE => 'Selesai dipindah',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_FAILED => 'Gagal dibuat',
        self::STATUS_CANCELLED => 'Batal',
    ];

    private const BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning',
        self::STATUS_APPROVED => 'bg-info-subtle text-info',
        self::STATUS_DONE => 'bg-success-subtle text-success',
        self::STATUS_REJECTED => 'bg-secondary-subtle text-secondary',
        self::STATUS_FAILED => 'bg-danger-subtle text-danger',
        self::STATUS_CANCELLED => 'bg-light text-dark',
    ];

    /** Semua kolom diisi lewat RescheduleService (forceFill), tidak pernah dari form. */
    protected $fillable = [];

    protected function casts(): array
    {
        return ['slot_start_at' => 'datetime', 'slot_end_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class, 'session_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(ScheduleAttendance::class, 'attendance_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ScheduleStudent::class, 'student_id');
    }

    public function slotTeacher(): BelongsTo
    {
        return $this->belongsTo(ScheduleTeacher::class, 'slot_teacher_id');
    }

    public function slotRoom(): BelongsTo
    {
        return $this->belongsTo(ScheduleRoom::class, 'slot_room_id');
    }

    public function makeupSession(): BelongsTo
    {
        return $this->belongsTo(ScheduleSession::class, 'makeup_session_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::BADGES[$this->status] ?? 'bg-light text-dark';
    }

    /** "Senin, 30 Nov · 15.00–15.30" */
    public function slotLabel(): string
    {
        return $this->slot_start_at->translatedFormat('l, d M').' · '.$this->slot_start_at->format('H.i').'–'.$this->slot_end_at->format('H.i');
    }
}
