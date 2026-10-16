<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AI Bot per device dengan API key milik user. Alamat API tetap per penyedia (tidak bisa diisi bebas). */
class WaAiBot extends Model
{
    use HasUuids;

    public const DRIVERS = AiProvider::DRIVERS + [
        'openrouter' => ['OpenRouter', 'https://openrouter.ai/api/v1', 'openai/gpt-4o-mini'],
    ];

    protected $fillable = ['name', 'driver', 'model', 'system_prompt', 'knowledge', 'max_tokens', 'handover_minutes', 'is_active'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'is_active' => 'boolean'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function baseUrl(): string
    {
        return self::DRIVERS[$this->driver][1] ?? '';
    }

    public function maskedKey(): string
    {
        $key = (string) $this->api_key;

        return strlen($key) > 8 ? '••••••••'.substr($key, -4) : '••••••••';
    }
}
