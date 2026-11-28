<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu percakapan di Tools › Inbox: DM (Instagram/Facebook) atau satu komentar utama beserta balasannya.
 * handler ai = AI boleh membalas (kalau mode AI otomatis); admin = admin memegang, AI berhenti.
 */
class SocialThread extends Model
{
    use HasUuids;

    public const KIND_DM = 'dm';

    public const KIND_COMMENT = 'comment';

    /** Meta hanya mengizinkan balasan DM dalam 24 jam sejak pesan terakhir orang tersebut. */
    public const DM_WINDOW_HOURS = 24;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'needs_human' => 'boolean',
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SocialMessage::class);
    }

    public function displayName(): string
    {
        return $this->contact_name ?: ($this->contact_handle ? '@'.ltrim($this->contact_handle, '@') : 'Pengguna '.$this->account?->platformLabel());
    }

    public function kindLabel(): string
    {
        return $this->kind === self::KIND_DM ? 'Pesan' : 'Komentar';
    }

    /** DM yang sudah lewat 24 jam tidak bisa dibalas lagi (aturan Meta). Komentar tidak punya batas waktu. */
    public function canReply(): bool
    {
        if ($this->kind !== self::KIND_DM) {
            return true;
        }

        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(self::DM_WINDOW_HOURS));
    }

    public function windowEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->kind === self::KIND_DM ? $this->last_inbound_at?->copy()->addHours(self::DM_WINDOW_HOURS) : null;
    }
}
