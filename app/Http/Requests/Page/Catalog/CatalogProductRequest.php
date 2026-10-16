<?php

namespace App\Http\Requests\Page\Catalog;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CatalogProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Kategori wajib milik katalog yang sama.
            'category_id' => ['nullable', Rule::exists(CatalogCategory::class, 'id')->where('page_id', $this->route('page'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', Rule::in(array_keys(CatalogProduct::STATUS_LABELS))],
            'images' => ['nullable', 'array', 'max:'.CatalogProduct::MAX_IMAGES],
            'images.*.id' => ['nullable', 'uuid'],
            'images.*.name' => ['nullable', 'string', 'max:150'],
            'images.*.description' => ['nullable', 'string', 'max:255'],
            'images.*.status' => ['required', Rule::in(array_keys(CatalogProductImage::STATUS_LABELS))],
            'images.*.file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (array_values($this->input('images', [])) as $i => $row) {
                $key = array_keys($this->input('images', []))[$i];

                if (blank($row['id'] ?? null) && ! $this->file("images.{$key}.file")) {
                    $validator->errors()->add("images.{$key}.file", 'Foto baris '.($i + 1).' belum dipilih.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'sale_price.lt' => 'Harga coret harus lebih murah dari harga normal.',
            'images.max' => 'Maksimal '.CatalogProduct::MAX_IMAGES.' foto per produk.',
            'images.*.file.max' => 'Ukuran foto maksimal 5 MB.',
            'images.*.file.image' => 'File harus berupa gambar (JPG, PNG, atau WebP).',
        ];
    }

    public function attributes(): array
    {
        return ['category_id' => 'kategori', 'name' => 'nama produk', 'price' => 'harga', 'sale_price' => 'harga coret', 'stock' => 'stok', 'description' => 'deskripsi'];
    }

    public function productData(): array
    {
        return $this->safe()->only(['category_id', 'name', 'description', 'price', 'sale_price', 'stock', 'status']);
    }

    /** Baris foto sesuai urutan di layar, beserta file barunya (kalau ada). */
    public function imageRows(): array
    {
        return collect($this->validated('images') ?? [])
            ->map(fn ($row, $key) => [
                'id' => $row['id'] ?? null,
                'name' => $row['name'] ?? null,
                'description' => $row['description'] ?? null,
                'status' => $row['status'],
                'file' => $this->file("images.{$key}.file"),
            ])
            ->values()
            ->all();
    }
}
