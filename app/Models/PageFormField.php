<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class PageFormField extends Model
{
    use HasUuids;

    public const TYPES = [
        'text' => 'Teks Singkat',
        'textarea' => 'Paragraf',
        'email' => 'Email',
        'phone' => 'WhatsApp / No. HP',
        'number' => 'Angka',
        'date' => 'Tanggal',
        'select' => 'Pilihan (dropdown)',
        'radio' => 'Pilihan Tunggal',
        'checkbox' => 'Centang (boleh lebih dari satu)',
        'file' => 'Upload File',
    ];

    public const WITH_OPTIONS = ['select', 'radio', 'checkbox'];

    // Field yang ditandai owner untuk disimpan ke Buku Kontak, dan jenis field yang cocok.
    public const CONTACT_FIELDS = [
        'name' => ['Nama', ['text']],
        'phone' => ['WhatsApp', ['phone', 'text']],
        'email' => ['Email', ['email', 'text']],
    ];

    public const FILE_MIMES = 'jpg,jpeg,png,webp,pdf';
    public const FILE_MAX_KB = 5120;

    protected $fillable = ['type', 'label', 'placeholder', 'help_text', 'options', 'is_required', 'contact_field'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'sort_order' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_category_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::WITH_OPTIONS, true);
    }

    /** Aturan validasi jawaban pendaftar, dibentuk dari pengaturan field ini. */
    public function rules(): array
    {
        $base = [$this->is_required ? 'required' : 'nullable'];
        $options = $this->options ?? [];

        return [...$base, ...match ($this->type) {
            'textarea' => ['string', 'max:5000'],
            'email' => ['email', 'max:255'],
            'phone' => ['string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'number' => ['numeric', 'max:999999999999'],
            'date' => ['date'],
            'select', 'radio' => [Rule::in($options)],
            'checkbox' => ['array', Rule::in($options)],
            'file' => ['file', 'mimes:'.self::FILE_MIMES, 'max:'.self::FILE_MAX_KB],
            default => ['string', 'max:255'],
        }];
    }
}
