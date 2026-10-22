<?php

namespace App\Http\Requests\Superadmin\PaymentGateway;

use App\Models\PaymentGatewaySetting;
use App\Support\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccess('superadmin.payment-gateway', Menu::ABILITY_MANAGE);
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([PaymentGatewaySetting::MODE_SANDBOX, PaymentGatewaySetting::MODE_PRODUCTION])],
            'is_active' => ['boolean'],
            'sandbox_merchant_code' => ['nullable', 'string', 'max:50'],
            'sandbox_api_key' => ['nullable', 'string', 'max:255'],
            'production_merchant_code' => ['nullable', 'string', 'max:50'],
            'production_api_key' => ['nullable', 'string', 'max:255'],
            'expiry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'min_deposit' => ['required', 'integer', 'min:1000'],
            'max_deposit' => ['required', 'integer', 'gte:min_deposit'],
            'settlement_default_days' => ['required', 'integer', 'min:0', 'max:14'],
            'settlement_methods' => ['nullable', 'string', 'max:5000'],
            'settlement_holidays' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'expiry_minutes' => 'batas waktu bayar',
            'min_deposit' => 'minimal top up',
            'max_deposit' => 'maksimal top up',
            'settlement_default_days' => 'masa settlement default',
            'settlement_methods' => 'masa settlement per metode',
            'settlement_holidays' => 'tanggal libur',
        ];
    }
}
