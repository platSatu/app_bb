<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    use HasUuids;

    // driver => [label, base url default, contoh model]
    public const DRIVERS = [
        'openai' => ['OpenAI', 'https://api.openai.com/v1', 'gpt-4o-mini'],
        'deepseek' => ['DeepSeek', 'https://api.deepseek.com', 'deepseek-chat'],
        'gemini' => ['Google Gemini', 'https://generativelanguage.googleapis.com/v1beta', 'gemini-1.5-flash'],
        'anthropic' => ['Anthropic Claude', 'https://api.anthropic.com/v1', 'claude-3-5-haiku-latest'],
        // Classai Router: satu key untuk banyak model (Claude, GPT, DeepSeek, GLM, dll.), format pesan Anthropic.
        'classai' => ['Classai Router', 'https://router.classai.id/v1', 'claude-sonnet-5.5'],
    ];

    /** ID model yang boleh disimpan/dikirim ke penyedia (huruf, angka, titik, titik dua, garis miring, minus, @). */
    public const MODEL_PATTERN = '/^[A-Za-z0-9._:\/@-]{1,100}$/';

    protected $fillable = ['name', 'driver', 'model', 'base_url'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'is_active' => 'boolean'];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }

    public function driverLabel(): string
    {
        return self::DRIVERS[$this->driver][0] ?? $this->driver;
    }

    public function isClassai(): bool
    {
        return $this->driver === 'classai';
    }

    /** Driver yang memakai format Anthropic Messages (/messages, header x-api-key). */
    public function usesAnthropicFormat(): bool
    {
        return in_array($this->driver, ['anthropic', 'classai'], true);
    }

    public function baseUrl(): string
    {
        return rtrim($this->base_url ?: (self::DRIVERS[$this->driver][1] ?? ''), '/');
    }
}
