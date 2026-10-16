<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Nomor WhatsApp milik satu branch. Status & sesi dikelola go_bizbos. */
class WaDevice extends Model
{
    use HasUuids;

    public const STATUS_LABELS = [
        'connected' => ['Terhubung', 'success', 'wa-dot-connected'],
        'connecting' => ['Menyambung...', 'warning', 'wa-dot-pending'],
        'pairing' => ['Menunggu scan', 'warning', 'wa-dot-pending'],
        'disconnected' => ['Terputus', 'danger', 'wa-dot-disconnected'],
        'logged_out' => ['Belum terhubung', 'secondary', 'wa-dot-disconnected'],
        'banned' => ['Dibatasi WhatsApp', 'danger', 'wa-dot-disconnected'],
        'replaced' => ['Dibuka di server lain', 'danger', 'wa-dot-disconnected'],
    ];

    protected $fillable = ['name', 'daily_cap'];

    protected function casts(): array
    {
        return [
            'warmup_started_at' => 'datetime',
            'banned_until' => 'datetime',
            'last_connected_at' => 'datetime',
            'sent_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(WaChat::class, 'device_id');
    }

    public function apiKey(): HasOne
    {
        return $this->hasOne(WaApiKey::class, 'device_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /** Pernah ditautkan (sesi tersimpan di go_bizbos). */
    public function isLinked(): bool
    {
        return $this->jid !== null;
    }

    public function statusMeta(): array
    {
        [$label, $color, $dot] = self::STATUS_LABELS[$this->status] ?? self::STATUS_LABELS['disconnected'];

        return ['label' => $label, 'color' => $color, 'dot' => $dot];
    }

    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'push_name' => $this->push_name,
            'branch' => $this->branch?->name,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'linked' => $this->isLinked(),
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            ...$this->statusMeta(),
        ];
    }
}
