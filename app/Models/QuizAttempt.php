<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'max_score' => 'integer',
            'correct_count' => 'integer',
            'is_late' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id')->orderBy('sort_order');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** Batas akhir pengerjaan, null = tanpa batas waktu. */
    public function deadline(?int $minutes): ?\Illuminate\Support\Carbon
    {
        return $minutes ? $this->started_at->copy()->addMinutes($minutes) : null;
    }

    public function durationLabel(): string
    {
        if (! $this->finished_at) {
            return '-';
        }

        $seconds = (int) $this->started_at->diffInSeconds($this->finished_at);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
