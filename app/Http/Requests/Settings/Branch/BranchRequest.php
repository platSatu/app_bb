<?php

namespace App\Http\Requests\Settings\Branch;

use App\Models\User;
use App\Models\UserBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'handphone' => $this->filled('handphone') ? User::normalizePhone($this->input('handphone')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'handphone' => ['nullable', 'regex:/^62[1-9][0-9]{8,12}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([UserBranch::STATUS_ACTIVE, UserBranch::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, - atau tanda kurung.',
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama branch', 'phone' => 'telepon', 'address' => 'alamat', 'city' => 'kota', 'description' => 'deskripsi'];
    }
}
