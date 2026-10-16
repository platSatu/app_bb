<?php

namespace App\Http\Requests\Superadmin\Withdrawal;

use Illuminate\Foundation\Http\FormRequest;

class DisbursementSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:sandbox,production'],
            'sandbox_user_id' => ['nullable', 'digits_between:1,20'],
            'sandbox_email' => ['nullable', 'email', 'max:255'],
            'sandbox_secret_key' => ['nullable', 'string', 'max:255'],
            'production_user_id' => ['nullable', 'digits_between:1,20'],
            'production_email' => ['nullable', 'email', 'max:255'],
            'production_secret_key' => ['nullable', 'string', 'max:255'],
            'withdrawal_fee' => ['required', 'integer', 'min:0', 'max:100000'],
            'min_amount' => ['required', 'integer', 'min:10000', 'max:100000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
