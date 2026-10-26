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
        // "1.500.000" -> 1500000. Tanda minus dibiarkan supaya ditolak validasi, bukan diam-diam jadi angka positif.
        $amount = (string) $this->input('amount');
        $this->merge(['amount' => str_contains($amount, '-') ? $amount : preg_replace('/\D/', '', $amount)]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            // Biaya yang dilihat user; kalau admin baru mengubahnya, pengajuan ditahan dulu.
            'fee' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'jumlah tarik saldo'];
    }
}
