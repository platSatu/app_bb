<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['account_number' => preg_replace('/\D/', '', (string) $this->input('account_number'))]);
    }

    public function rules(): array
    {
        return [
            'bank_code' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'digits_between:5,25'],
        ];
    }

    public function attributes(): array
    {
        return ['bank_code' => 'bank', 'account_number' => 'nomor rekening'];
    }
}
