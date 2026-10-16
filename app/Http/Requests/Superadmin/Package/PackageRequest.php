<?php

namespace App\Http\Requests\Superadmin\Package;

use App\Models\CategoryApplication;
use App\Models\Package;
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
        ];
    }

    public function attributes(): array
    {
        return [
            'category_application_id' => 'kategori aplikasi',
            'name' => 'nama package',
            'price' => 'harga',
            'duration' => 'durasi',
            'description' => 'deskripsi',
        ];
    }
}
