<?php

namespace App\Http\Requests\Superadmin\CategoryApplication;

use App\Models\CategoryApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryApplicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(CategoryApplication::STATUS_LABELS))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return ['image.max' => 'Ukuran gambar maksimal 2 MB.'];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'description' => 'deskripsi', 'image' => 'gambar'];
    }
}
