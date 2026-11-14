<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Posisi kontak di dalam chatbot flow (satu sesi per chat). */
class WaBotSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(WaChatbotFlow::class, 'flow_id');
    }

    /** Dipakai NudgeIdleBotSessions untuk menyapa & menutup chat yang menggantung. */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(WaChat::class, 'chat_id');
    }
}
