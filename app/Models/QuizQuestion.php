<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuizQuestion extends Model
{
    use HasActiveStatus, HasUuids;

    public const TYPE_TEXT = 'text';
    public const TYPE_SINGLE = 'single';
    public const TYPE_TEXTAREA = 'textarea';

    public const TYPES = [
        self::TYPE_SINGLE => 'Single Choice',
        self::TYPE_TEXT => 'Text Line',
        self::TYPE_TEXTAREA => 'Textarea (tidak dinilai)',
    ];

    protected $fillable = ['type', 'question', 'answer_keys', 'points', 'status'];

    // Kunci jawaban tidak boleh ikut ter-serialize ke publik.
    protected $hidden = ['answer_keys'];

    protected function casts(): array
    {
        return ['answer_keys' => 'array', 'points' => 'integer', 'sort_order' => 'integer'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id')->orderBy('sort_order');
    }

    public function isScored(): bool
    {
        return $this->type !== self::TYPE_TEXTAREA;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Text line: cocok kalau sama dengan salah satu kunci (abaikan huruf besar/kecil & spasi berlebih). */
    public function matchesText(?string $answer): bool
    {
        $normalize = fn (?string $value) => Str::of((string) $value)->squish()->lower()->value();
        $answer = $normalize($answer);

        return $answer !== '' && collect($this->answer_keys ?? [])->contains(fn ($key) => $normalize($key) === $answer);
    }
}
