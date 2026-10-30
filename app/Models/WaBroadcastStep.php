<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan dalam Message Bertahap: dikirim pada tanggal mulai + delay_days, ke semua tujuan. */
class WaBroadcastStep extends Model
{
    use HasUuids;

    protected $fillable = ['position', 'delay_days', 'body'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'delay_days' => 'integer'];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WaBroadcast::class, 'broadcast_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'wa_template_id');
    }
}
