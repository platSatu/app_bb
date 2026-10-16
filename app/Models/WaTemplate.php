<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Template pesan WhatsApp. Teks memakai format WA: *tebal* _miring_ ~coret~ ```mono```. */
class WaTemplate extends Model
{
    use HasUuids;

    public const DISK = 'local';

    protected $fillable = ['name', 'header', 'body', 'footer', 'link', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'file_size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $template) => $template->deleteFile());
    }

    /** Teks siap kirim: header tebal, isi, footer miring, lalu link. */
    public function toText(): string
    {
        return collect([
            $this->header ? '*'.$this->header.'*' : null,
            $this->body,
            $this->footer ? '_'.$this->footer.'_' : null,
            $this->link,
        ])->filter()->implode("\n\n");
    }

    public function fileKind(): ?string
    {
        if (! $this->file_mime) {
            return null;
        }

        return match (strtok($this->file_mime, '/')) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            default => 'document',
        };
    }

    public function deleteFile(): void
    {
        if ($this->file_path) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }
}
