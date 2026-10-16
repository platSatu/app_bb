<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Kredensial API kirim WA per device. Secret terenkripsi, hanya ditampilkan sekali saat dibuat. */
class WaApiKey extends Model
{
    use HasUuids;

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WaApiLog::class, 'api_key_id');
    }

    public static function newApiKey(): string
    {
        return 'wa_'.(app()->isProduction() ? 'live' : 'test').'_'.Str::random(40);
    }

    public static function newSecret(): string
    {
        return 'wsk_'.Str::random(48);
    }
}
