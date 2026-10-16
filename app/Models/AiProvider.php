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
    ];

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

    public function baseUrl(): string
    {
        return rtrim($this->base_url ?: (self::DRIVERS[$this->driver][1] ?? ''), '/');
    }
}
