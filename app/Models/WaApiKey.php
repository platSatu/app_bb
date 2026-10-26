<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Kredensial API kirim WA per device (Token + Secret Key). Secret hanya disimpan hash-nya, ditampilkan sekali saat dibuat. */
class WaApiKey extends Model
{
    use HasUuids;

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WaApiLog::class, 'api_key_id');
    }

    /** Buat Secret Key baru, simpan hash-nya, kembalikan teks aslinya untuk ditampilkan sekali. */
    public function issueSecret(): string
    {
        $secret = 'wsk_'.Str::random(48);
        $this->forceFill(['secret_hash' => self::hashSecret($secret)]);

        return $secret;
    }

    public function secretMatches(string $secret): bool
    {
        return $this->secret_hash !== null && hash_equals($this->secret_hash, self::hashSecret($secret));
    }

    public static function hashSecret(string $secret): string
    {
        // Secret acak 48 karakter, SHA-256 sudah cukup (tidak perlu hash lambat seperti password).
        return hash('sha256', $secret);
    }

    public static function newApiKey(): string
    {
        return 'wa_'.(app()->isProduction() ? 'live' : 'test').'_'.Str::random(40);
    }
}
