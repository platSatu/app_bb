<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pesan WhatsApp. Ditulis go_bizbos; media_meta berisi kunci unduh, jangan dikirim ke browser. */
class WaMessage extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    protected $hidden = ['media_meta'];

    protected function casts(): array
    {
        return ['from_me' => 'boolean', 'sent_at' => 'datetime', 'meta' => 'array'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(WaChat::class, 'chat_id');
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(WaPollVote::class, 'message_id');
    }

    public function hasMedia(): bool
    {
        return $this->media_path !== null || $this->getRawOriginal('media_meta') !== null;
    }
}
