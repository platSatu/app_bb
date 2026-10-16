<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Satu putaran kirim. Status & hitungan diperbarui go_bizbos. */
class WaCampaign extends Model
{
    use HasUuids;

    public const STATUSES = [
        'draft' => ['Menyiapkan', 'secondary'],
        'running' => ['Mengirim', 'primary'],
        'paused' => ['Dijeda', 'warning'],
        'done' => ['Selesai', 'success'],
        'cancelled' => ['Dibatalkan', 'danger'],
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['draft'];
    }
}
