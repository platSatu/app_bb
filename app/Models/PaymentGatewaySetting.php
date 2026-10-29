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
        'expiry_minutes', 'min_deposit', 'max_deposit', 'is_active', 'settlement_default_days',
        'qris_fee_percent',
    ];

    /** Kode metode QRIS di Duitku (dokumentasi API Duitku: SP ShopeePay, NQ Nobu, SQ Nusapay). Bisa diubah superadmin. */
    public const DEFAULT_QRIS_METHODS = ['SP', 'NQ', 'SQ'];

    /** Potongan QRIS default (%) kalau belum diatur. */
    public const DEFAULT_QRIS_FEE_PERCENT = 0.7;

    /** Kode metode Duitku => [nama, hari kerja settlement]. null = ikut default. Bisa diubah superadmin. */
    public const DEFAULT_SETTLEMENT_METHODS = [
        'BC' => ['name' => 'BCA Virtual Account', 'days' => null],
        'M2' => ['name' => 'Mandiri Virtual Account', 'days' => null],
        'I1' => ['name' => 'BNI Virtual Account', 'days' => null],
        'BR' => ['name' => 'BRI Virtual Account', 'days' => null],
        'BT' => ['name' => 'Permata Virtual Account', 'days' => null],
        'B1' => ['name' => 'CIMB Niaga Virtual Account', 'days' => null],
        'A1' => ['name' => 'ATM Bersama', 'days' => null],
        'OV' => ['name' => 'OVO', 'days' => null],
        'DA' => ['name' => 'DANA', 'days' => null],
        'SA' => ['name' => 'ShopeePay App', 'days' => null],
        'SP' => ['name' => 'ShopeePay QRIS', 'days' => null],
        'SQ' => ['name' => 'Nusapay QRIS', 'days' => null],
        'NQ' => ['name' => 'Nobu QRIS', 'days' => null],
        'FT' => ['name' => 'Retail (Alfamart/Pos)', 'days' => 4],
        'IR' => ['name' => 'Indomaret', 'days' => 4],
        'VC' => ['name' => 'Kartu Kredit', 'days' => null],
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
            'settlement_default_days' => 'integer',
            'settlement_methods' => 'array',
            'settlement_holidays' => 'array',
            'qris_fee_percent' => 'float',
            'qris_fee_methods' => 'array',
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

    /** @return array<string, array{name: string, days: ?int}> */
    public function settlementMethods(): array
    {
        return $this->settlement_methods ?? self::DEFAULT_SETTLEMENT_METHODS;
    }

    /** Hari kerja sampai uang bisa ditarik. Metode tidak diketahui = masa terlama (paling aman). */
    public function settlementDays(?string $method): int
    {
        $default = (int) ($this->settlement_default_days ?? 2);
        $methods = $this->settlementMethods();

        if ($method === null || $method === '') {
            return max($default, ...array_map(fn ($m) => (int) ($m['days'] ?? $default), $methods ?: [['days' => $default]]));
        }

        return (int) ($methods[strtoupper($method)]['days'] ?? $default);
    }

    public function methodName(?string $method): ?string
    {
        return $method ? ($this->settlementMethods()[strtoupper($method)]['name'] ?? strtoupper($method)) : null;
    }

    /** @return array<int, string> kode metode yang dihitung QRIS */
    public function qrisMethods(): array
    {
        return array_values(array_map('strtoupper', $this->qris_fee_methods ?? self::DEFAULT_QRIS_METHODS));
    }

    public function qrisFeePercent(): float
    {
        return max(0.0, (float) ($this->qris_fee_percent ?? self::DEFAULT_QRIS_FEE_PERCENT));
    }

    public function isQris(?string $method): bool
    {
        return $method !== null && $method !== '' && in_array(strtoupper($method), $this->qrisMethods(), true);
    }

    /**
     * Potongan QRIS (rupiah, dibulatkan ke atas) dari nominal yang dibayar customer.
     * Metode selain QRIS = 0, karena biayanya sudah ditanggung customer di Duitku.
     */
    public function qrisFeeFor(?string $method, int $paidAmount): int
    {
        if ($paidAmount <= 0 || ! $this->isQris($method)) {
            return 0;
        }

        return (int) ceil(round($paidAmount * $this->qrisFeePercent() / 100, 4));
    }

    public function isReady(): bool
    {
        return $this->is_active && filled($this->merchantCode()) && filled($this->apiKey());
    }
}
