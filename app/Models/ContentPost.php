<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu konten sosmed dari Agent Konten (Tools › Konten Harian).
 * Alur: draft -> approved (sudah dibaca) -> ready (media jadi) -> scheduled -> published | partial | failed.
 * Status kiriman per akun ada di ContentPublication.
 */
class ContentPost extends Model
{
    use HasUuids;

    /** format => [label, ikon, platform yang bisa menerima] */
    public const FORMATS = [
        'reel' => ['Reels / video', 'ri-movie-2-line', ['instagram', 'facebook', 'tiktok', 'youtube']],
        'carousel' => ['Carousel', 'ri-gallery-line', ['instagram', 'facebook', 'tiktok']],
        'image' => ['Gambar', 'ri-image-line', ['instagram', 'facebook', 'tiktok']],
    ];

    public const STATUSES = [
        'draft' => ['Draf', 'bg-secondary-subtle text-secondary'],
        'approved' => ['Disetujui', 'bg-info-subtle text-info'],
        'ready' => ['Siap upload', 'bg-primary-subtle text-primary'],
        'scheduled' => ['Terjadwal', 'bg-warning-subtle text-warning'],
        'published' => ['Terposting', 'bg-success-subtle text-success'],
        'partial' => ['Sebagian gagal', 'bg-danger-subtle text-danger'],
        'failed' => ['Gagal upload', 'bg-danger-subtle text-danger'],
    ];

    public const MEDIA_STATUSES = [
        'queued' => ['Media: antre', 'bg-light text-dark'],
        'processing' => ['Media: dibuat…', 'bg-info-subtle text-info'],
        'done' => ['Media: jadi', 'bg-success-subtle text-success'],
        'failed' => ['Media: gagal', 'bg-danger-subtle text-danger'],
    ];

    /** Status yang teksnya masih boleh diubah (belum dijadwalkan / diposting). */
    public const EDITABLE = ['draft', 'approved', 'ready'];

    public const MAX_SLIDES = 10;

    protected $fillable = [
        'batch_id', 'publish_date', 'publish_time', 'format', 'pillar', 'title', 'hook', 'script', 'slides',
        'caption', 'hashtags', 'visual_prompt',
    ];

    protected function casts(): array
    {
        return [
            'publish_date' => 'date',
            'slides' => 'array',
            'approved_at' => 'datetime',
            'media' => 'array',
            'media_ai' => 'boolean',
            'media_cost_micro_rp' => 'integer',
            'media_started_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'options' => 'array',
        ];
    }

    public function publications(): HasMany
    {
        return $this->hasMany(ContentPublication::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeEditable(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), self::EDITABLE);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function isVideo(): bool
    {
        return $this->format === 'reel';
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format][0] ?? $this->format;
    }

    public function formatIcon(): string
    {
        return self::FORMATS[$this->format][1] ?? 'ri-file-line';
    }

    /** @return list<string> platform yang bisa menerima format ini. */
    public function allowedPlatforms(): array
    {
        return self::FORMATS[$this->format][2] ?? [];
    }

    /** @return list<array{path: string, mime: string, size: int}> */
    public function mediaFiles(): array
    {
        return array_values(array_filter((array) $this->media, fn ($m) => is_array($m) && filled($m['path'] ?? null)));
    }

    public function hasMedia(): bool
    {
        return $this->media_status === 'done' && $this->mediaFiles() !== [];
    }

    /** Waktu tayang dari tanggal + jam (zona waktu aplikasi, WIB). */
    public function plannedAt(): Carbon
    {
        return Carbon::parse($this->publish_date->toDateString().' '.$this->publish_time);
    }

    /** Caption + hashtag, siap tempel ke platform. */
    public function fullCaption(int $max = 2200): string
    {
        $text = trim(trim((string) $this->caption)."\n\n".trim((string) $this->hashtags));

        return mb_substr($text, 0, $max);
    }

    /** Slide carousel dalam format isian: satu baris "Judul | isi". */
    public function slidesText(): string
    {
        return collect((array) $this->slides)->map(fn ($s) => trim(($s['heading'] ?? '').' | '.($s['body'] ?? ''), ' |'))->implode("\n");
    }

    /**
     * Status konten dari status kiriman per akun (dipanggil setelah kiriman berubah).
     * Masih ada yang antre/berjalan = tetap "scheduled".
     */
    public function refreshPublishStatus(): void
    {
        $statuses = $this->publications()->pluck('status');

        if ($statuses->isEmpty()) {
            if ($this->status === 'scheduled') {
                $this->forceFill(['status' => $this->hasMedia() ? 'ready' : 'approved', 'scheduled_at' => null])->save();
            }

            return;
        }

        if ($statuses->contains(fn ($s) => in_array($s, ContentPublication::ACTIVE, true))) {
            $status = 'scheduled';
        } elseif ($statuses->every(fn ($s) => $s === 'published')) {
            $status = 'published';
        } elseif ($statuses->contains('published')) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}
