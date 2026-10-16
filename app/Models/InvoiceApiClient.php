<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kredensial API Invoice per branch. Secret API dan secret webhook dipisah, disimpan
 * terenkripsi (perlu dibaca ulang untuk HMAC) dan hanya ditampilkan sekali saat dibuat.
 */
class InvoiceApiClient extends Model
{
    use HasUuids, ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.invoices';

    protected $fillable = ['webhook_url', 'allowed_origins', 'is_active'];

    protected $hidden = ['secret', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'allowed_origins' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(InvoiceWebhookLog::class, 'api_client_id');
    }

    public static function newApiKey(): string
    {
        return 'pk_'.(app()->isProduction() ? 'live' : 'test').'_'.Str::random(40);
    }

    public static function newSecret(string $prefix): string
    {
        return $prefix.'_'.Str::random(48);
    }

    /** Origin website yang boleh menerima kabar dari popup bayar (persis: skema + domain + port). */
    public function allowsOrigin(?string $origin): bool
    {
        return $origin !== null && in_array(rtrim($origin, '/'), $this->allowed_origins ?? [], true);
    }
}
