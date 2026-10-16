<?php

namespace App\Http\Requests\Account;

use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Http\FormRequest;

class TopUpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // "Rp 50.000" / "50.000" -> 50000
        $this->merge(['amount' => preg_replace('/\D/', '', (string) $this->input('amount'))]);
    }

    public function rules(): array
    {
        $setting = PaymentGatewaySetting::duitku();

        return ['amount' => ['required', 'integer', 'min:'.$setting->min_deposit, 'max:'.$setting->max_deposit]];
    }

    public function messages(): array
    {
        $setting = PaymentGatewaySetting::duitku();
        $format = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');

        return [
            'amount.required' => 'Masukkan nominal top up dulu ya.',
            'amount.min' => 'Minimal top up '.$format($setting->min_deposit).'.',
            'amount.max' => 'Maksimal top up '.$format($setting->max_deposit).' per transaksi.',
        ];
    }
}
