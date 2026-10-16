<?php

namespace App\Http\Requests\Settings\Company;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'handphone' => $this->filled('handphone') ? User::normalizePhone($this->input('handphone')) : null,
        ]);
    }

    public function rules(): array
    {
        $phoneRule = ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'];

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => $phoneRule,
            'fax' => $phoneRule,
            'email' => ['nullable', 'email', 'max:255'],
            'handphone' => ['nullable', 'regex:/^62[1-9][0-9]{8,12}$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, - atau tanda kurung.',
            'fax.regex' => 'Nomor fax hanya boleh berisi angka, spasi, +, - atau tanda kurung.',
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama perusahaan', 'phone' => 'telepon', 'fax' => 'fax', 'description' => 'deskripsi'];
    }
}
