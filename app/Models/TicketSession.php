<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sesi/kelas di dalam satu event tiket. Absensi hanya bisa discan di dalam jendela waktunya:
 * dibuka open_before_minutes sebelum mulai, ditutup late_minutes setelah mulai (atau saat sesi selesai).
 */
class TicketSession extends Model
{
    use HasActiveStatus, HasUuids;

    public const STATE_UPCOMING = 'upcoming';
    public const STATE_OPEN = 'open';
    public const STATE_CLOSED = 'closed';
    public const STATE_INACTIVE = 'inactive';

    protected $fillable = ['name', 'room', 'starts_at', 'ends_at', 'open_before_minutes', 'late_minutes', 'category_ids', 'status'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'open_before_minutes' => 'integer',
            'late_minutes' => 'integer',
            'category_ids' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TicketSessionAttendance::class, 'session_id');
    }

    public function opensAt(): Carbon
    {
        return $this->starts_at->copy()->subMinutes($this->open_before_minutes);
    }

    public function closesAt(): Carbon
    {
        $late = $this->late_minutes === null ? null : $this->starts_at->copy()->addMinutes($this->late_minutes);

        // Batas terlambat tidak boleh melewati jam selesai sesi.
        return $late && $late->lt($this->ends_at) ? $late : $this->ends_at->copy();
    }

    public function state(?Carbon $now = null): string
    {
        $now ??= now();

        return match (true) {
            ! $this->isActive() => self::STATE_INACTIVE,
            $now->lt($this->opensAt()) => self::STATE_UPCOMING,
            $now->gt($this->closesAt()) => self::STATE_CLOSED,
            default => self::STATE_OPEN,
        };
    }

    /** Kalimat singkat yang ramah untuk petugas & admin. */
    public function stateLabel(?Carbon $now = null): string
    {
        return match ($this->state($now)) {
            self::STATE_INACTIVE => 'Sedang dinonaktifkan',
            self::STATE_UPCOMING => 'Absensi dibuka '.$this->opensAt()->translatedFormat('d M, H:i'),
            self::STATE_CLOSED => 'Absensi ditutup '.$this->closesAt()->translatedFormat('d M, H:i'),
            default => 'Absensi dibuka sampai '.$this->closesAt()->format('H:i'),
        };
    }

    public function stateBadge(?Carbon $now = null): string
    {
        return match ($this->state($now)) {
            self::STATE_OPEN => 'bg-success-subtle text-success',
            self::STATE_UPCOMING => 'bg-info-subtle text-info',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    /** Kosong = semua kategori tiket boleh ikut sesi ini. */
    public function allowsCategory(?string $categoryId): bool
    {
        return empty($this->category_ids) || in_array($categoryId, $this->category_ids, true);
    }

    /** Tiket yang berhak ikut sesi ini (untuk hitungan "belum hadir"). */
    public function eligibleTickets(): Builder
    {
        return Ticket::where('page_id', $this->page_id)
            ->when(! empty($this->category_ids), fn (Builder $q) => $q->whereIn('category_id', $this->category_ids));
    }

    public function timeRange(): string
    {
        $sameDay = $this->starts_at->isSameDay($this->ends_at);

        return $this->starts_at->translatedFormat('D, d M Y · H:i').' – '.($sameDay ? $this->ends_at->format('H:i') : $this->ends_at->translatedFormat('d M H:i'));
    }
}
