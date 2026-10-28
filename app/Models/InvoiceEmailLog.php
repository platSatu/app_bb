<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Satu email invoice ke customer; status diperbarui oleh InvoiceMail saat benar-benar dikirim. */
class InvoiceEmailLog extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public const KIND_LABELS = ['issued' => 'Tagihan baru', 'reminder' => 'Pengingat', 'paid' => 'Bukti lunas'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Terkirim',
            self::STATUS_FAILED => 'Gagal',
            default => 'Dalam antrean',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'bg-success-subtle text-success',
            self::STATUS_FAILED => 'bg-danger-subtle text-danger',
            default => 'bg-warning-subtle text-warning',
        };
    }
}
