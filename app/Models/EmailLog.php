<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** Satu email keluar. Dicatat otomatis oleh App\Support\EmailLogRecorder, tidak diisi manual. */
class EmailLog extends Model
{
    use HasUuids, MassPrunable;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_QUEUED => 'Dalam antrean',
        self::STATUS_SENT => 'Terkirim',
        self::STATUS_FAILED => 'Gagal',
    ];

    /** Riwayat lebih lama dari ini dihapus otomatis (model:prune). */
    public const KEEP_DAYS = 180;

    /** Lebih lama dari ini masih di antrean = worker kemungkinan mati. */
    public const STUCK_MINUTES = 10;

    public const TYPE_LABELS = [
        \App\Mail\InvoiceMail::class => 'Invoice ke customer',
        \App\Mail\InvoicePaidOwnerMail::class => 'Invoice lunas (owner)',
        \App\Mail\TicketOrderPaid::class => 'E-ticket',
        \App\Mail\SubscriptionReminderMail::class => 'Pengingat paket',
        \App\Notifications\ContactMessageReceivedNotification::class => 'Pesan kontak website',
        \App\Notifications\QueuedVerifyEmail::class => 'Verifikasi email',
        \App\Notifications\QueuedResetPassword::class => 'Reset password',
        \Illuminate\Auth\Notifications\VerifyEmail::class => 'Verifikasi email',
        \Illuminate\Auth\Notifications\ResetPassword::class => 'Reset password',
    ];

    protected $fillable = ['type', 'recipient', 'subject', 'status', 'error', 'job_uuid', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? class_basename($this->type);
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

    public function isStuck(): bool
    {
        return $this->status === self::STATUS_QUEUED && $this->created_at?->lt(now()->subMinutes(self::STUCK_MINUTES));
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
