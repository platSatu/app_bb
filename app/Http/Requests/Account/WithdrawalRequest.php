<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => preg_replace('/\D/', '', (string) $this->input('amount'))]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            // Biaya yang dilihat user; kalau admin baru mengubahnya, pengajuan ditahan dulu.
            'fee' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'jumlah tarik saldo'];
    }
}
