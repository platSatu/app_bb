<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu konten ke satu akun sosmed.
 * pending -> publishing (sedang dikirim, dikunci) -> processing (platform memproses) -> published | failed.
 * Perpindahan status selalu lewat claim() (atomik), jadi satu konten tidak pernah terkirim dua kali ke akun yang sama.
 */
class ContentPublication extends Model
{
    use HasUuids;

    public const STATUSES = [
        'pending' => ['Menunggu jadwal', 'bg-light text-dark'],
        'publishing' => ['Mengirim…', 'bg-info-subtle text-info'],
        'processing' => ['Diproses platform…', 'bg-info-subtle text-info'],
        'published' => ['Terposting', 'bg-success-subtle text-success'],
        'failed' => ['Gagal', 'bg-danger-subtle text-danger'],
    ];

    /** Status yang belum selesai. */
    public const ACTIVE = ['pending', 'publishing', 'processing'];

    protected $fillable = ['content_post_id', 'social_account_id', 'platform'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'published_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /** Pindah status secara atomik; false kalau status sudah diubah proses lain. */
    public function claim(string $from, string $to): bool
    {
        $claimed = static::whereKey($this->getKey())->where('status', $from)->update(['status' => $to, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $this->status = $to;
        }

        return $claimed;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-light text-dark';
    }
}
