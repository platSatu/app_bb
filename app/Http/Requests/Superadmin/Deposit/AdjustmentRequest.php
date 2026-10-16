<?php

namespace App\Http\Requests\Superadmin\Deposit;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email user', 'direction' => 'jenis koreksi', 'amount' => 'nominal', 'reason' => 'alasan'];
    }

    public function messages(): array
    {
        return ['email.exists' => 'User dengan email ini tidak ditemukan.'];
    }
}
