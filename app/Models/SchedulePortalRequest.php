<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pendaftaran lewat link portal. Menunggu persetujuan admin branch sebelum terhubung ke data murid/pengajar. */
class SchedulePortalRequest extends Model
{
    use BelongsToScheduleBranch, HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    private const BADGES = [
        self::STATUS_PENDING => 'bg-warning-subtle text-warning',
        self::STATUS_APPROVED => 'bg-success-subtle text-success',
        self::STATUS_REJECTED => 'bg-secondary-subtle text-secondary',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isStudent(): bool
    {
        return $this->type === SchedulePortalLink::TYPE_STUDENT;
    }

    public function typeLabel(): string
    {
        return SchedulePortalLink::TYPE_LABELS[$this->type] ?? $this->type;
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
