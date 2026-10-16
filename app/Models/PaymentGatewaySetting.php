<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    use HasUuids;

    public const PROVIDER_DUITKU = 'duitku';
    public const MODE_SANDBOX = 'sandbox';
    public const MODE_PRODUCTION = 'production';

    protected $fillable = [
        'mode', 'sandbox_merchant_code', 'production_merchant_code',
        'expiry_minutes', 'min_deposit', 'max_deposit', 'is_active',
    ];

    // API key terenkripsi di database dan tidak pernah ikut tampil/serialisasi.
    protected $hidden = ['sandbox_api_key', 'production_api_key'];

    protected function casts(): array
    {
        return [
            'sandbox_api_key' => 'encrypted',
            'production_api_key' => 'encrypted',
            'expiry_minutes' => 'integer',
            'min_deposit' => 'integer',
            'max_deposit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function duitku(): self
    {
        if ($setting = static::where('provider', self::PROVIDER_DUITKU)->first()) {
            return $setting;
        }

        $setting = new static();
        $setting->forceFill(['provider' => self::PROVIDER_DUITKU])->save();

        return $setting;
    }

    public function isSandbox(): bool
    {
        return $this->mode !== self::MODE_PRODUCTION;
    }

    public function merchantCode(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_merchant_code : $this->production_merchant_code;
    }

    public function apiKey(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_api_key : $this->production_api_key;
    }

    public function isReady(): bool
    {
        return $this->is_active && filled($this->merchantCode()) && filled($this->apiKey());
    }
}
