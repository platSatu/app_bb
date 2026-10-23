<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Integrasi pihak ketiga yang memicu pesan WhatsApp (saat ini: Google Form).
 * Pesan boleh memakai {{Judul Pertanyaan}} yang diganti jawaban pengisi form.
 */
class WaFormIntegration extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'device_id', 'phone_field', 'message', 'is_active'];

    protected $hidden = ['webhook_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_triggered_at' => 'datetime', 'trigger_count' => 'integer'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WaFormSubmission::class, 'integration_id');
    }

    public static function newToken(): string
    {
        return 'gf_'.Str::random(48);
    }

    public function webhookUrl(): string
    {
        return url('/api/v1/wa/google-form/'.$this->webhook_token);
    }
}
