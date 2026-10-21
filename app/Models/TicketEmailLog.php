<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Setiap pengiriman email e-ticket. "Terkirim" = sudah diterima server email (SMTP), bukan berarti sudah dibuka. */
class TicketEmailLog extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Dalam antrean',
        self::STATUS_SENT => 'Terkirim',
        self::STATUS_FAILED => 'Gagal',
    ];

    public const TRIGGER_LABELS = ['auto' => 'Otomatis setelah lunas', 'resend' => 'Kirim ulang oleh admin'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'bg-success-subtle text-success',
            self::STATUS_FAILED => 'bg-danger-subtle text-danger',
            default => 'bg-warning-subtle text-warning',
        };
    }

    public function triggerLabel(): string
    {
        return self::TRIGGER_LABELS[$this->trigger] ?? $this->trigger;
    }
}
