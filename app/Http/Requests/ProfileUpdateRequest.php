<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['handphone' => User::normalizePhone($this->input('handphone'))]);
    }

    /** Email sengaja tidak ada di sini: tidak bisa diubah sendiri oleh user. */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)->ignore($this->user())],
        ];
    }

    public function messages(): array
    {
        return [
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'handphone.unique' => 'Nomor WhatsApp ini sudah dipakai akun lain.',
        ];
    }
}
