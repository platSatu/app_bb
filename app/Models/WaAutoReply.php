<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaAutoReply extends Model
{
    use HasUuids;

    public const MATCH_TYPES = ['exact' => 'Sama persis', 'starts_with' => 'Diawali kata kunci', 'contains' => 'Mengandung kata kunci', 'any' => 'Semua pesan (sapaan / di luar jam kerja)'];

    protected $fillable = ['name', 'match_type', 'keywords', 'body', 'cooldown_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_active' => 'boolean'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }
}
