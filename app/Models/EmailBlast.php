<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kiriman Tools › Email Blast: tiap penerima dikirimi email terpisah, hasilnya di results. */
class EmailBlast extends Model
{
    use HasUuids;

    public const STATUSES = [
        'queued' => ['Antre', 'bg-secondary-subtle text-secondary'],
        'sending' => ['Mengirim', 'bg-info-subtle text-info'],
        'done' => ['Selesai', 'bg-success-subtle text-success'],
    ];

    protected $fillable = ['subject', 'body', 'recipients', 'created_by'];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'results' => 'array',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary-subtle text-secondary';
    }
}
