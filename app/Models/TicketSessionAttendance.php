<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 1 baris = 1 peserta hadir di 1 sesi. Ganda dicegah unique index (session_id, ticket_id). */
class TicketSessionAttendance extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TicketSession::class, 'session_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(TicketScanner::class, 'scanner_id');
    }
}
