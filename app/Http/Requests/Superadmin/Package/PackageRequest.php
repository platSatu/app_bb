<?php

namespace App\Http\Requests\Superadmin\Package;

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Support\Apps;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_application_id' => ['required', Rule::exists(CategoryApplication::class, 'id')],
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'duration' => ['required', 'integer', 'min:1', 'max:3650'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(array_keys(Package::STATUS_LABELS))],
            'apps' => ['required', 'array', 'min:1'],
            'apps.*' => ['required', 'distinct', Rule::in(Apps::keys())],
            'is_trial' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_trial' => $this->boolean('is_trial')]);
    }

    /** Paket trial selalu gratis. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_trial') && (int) $this->input('price') !== 0) {
                $validator->errors()->add('price', 'Paket trial harus Rp 0.');
            }
        });
    }

    public function messages(): array
    {
        return ['apps.required' => 'Pilih minimal satu layanan yang dibuka paket ini.'];
    }

    public function attributes(): array
    {
        return [
            'category_application_id' => 'kategori aplikasi',
            'name' => 'nama package',
            'price' => 'harga',
            'duration' => 'durasi',
            'description' => 'deskripsi',
            'apps' => 'layanan',
        ];
    }
}
