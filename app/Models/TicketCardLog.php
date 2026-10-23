<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat cetak ID card. Ditulis di transaksi yang sama dengan perubahan tiketnya. */
class TicketCardLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const ACTION_PRINT = 'print';
    public const ACTION_REPRINT = 'reprint';
    // Cetak ulang dengan QR baru: kartu lama otomatis tidak berlaku.
    public const ACTION_REPLACE = 'replace';

    public const ACTION_LABELS = [
        self::ACTION_PRINT => 'Cetak pertama',
        self::ACTION_REPRINT => 'Cetak ulang',
        self::ACTION_REPLACE => 'Cetak ulang + QR baru',
    ];

    public const SOURCE_KIOSK = 'kiosk';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_STAFF = 'petugas';

    public const SOURCE_LABELS = [
        self::SOURCE_KIOSK => 'Kiosk mandiri',
        self::SOURCE_ADMIN => 'Admin',
        self::SOURCE_STAFF => 'Petugas',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(TicketScanner::class, 'scanner_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
