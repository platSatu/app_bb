<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu percakapan di satu device. Dibuat & diperbarui go_bizbos. */
class WaChat extends Model
{
    use HasUuids;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'last_message_at' => 'datetime', 'bot_paused_until' => 'datetime', 'human_replied_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'chat_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(WaLabel::class, 'wa_chat_label', 'chat_id', 'label_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WaChatNote::class, 'chat_id');
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->phone ? '+'.$this->phone : ($this->is_group ? 'Grup' : 'Kontak'));
    }
}
