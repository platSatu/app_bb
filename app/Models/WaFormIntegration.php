<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Integrasi Google Form: setiap form yang masuk dibalas WhatsApp.
 * Opsional: registrasi ulang (QR + aplikasi petugas ber-PIN) dan pembayaran (invoice).
 * Pesan boleh memakai {{Judul Pertanyaan}}, {{link_bayar}}, {{link_qr}}, {{kode}}.
 */
class WaFormIntegration extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'device_id', 'phone_field', 'name_field', 'message', 'is_active', 'registration_enabled', 'payment_enabled', 'amount'];

    protected $hidden = ['webhook_token', 'checkin_token', 'checkin_pin_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'registration_enabled' => 'boolean', 'payment_enabled' => 'boolean',
            'amount' => 'integer', 'trigger_count' => 'integer', 'last_triggered_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
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

    public function checkinUrl(): ?string
    {
        return $this->checkin_token ? route('form-checkin.show', $this->checkin_token) : null;
    }
}
