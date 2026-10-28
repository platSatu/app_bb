<?php

namespace App\Http\Requests\Superadmin\Package;

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\PageType;
use App\Services\Subscription\PlanLimits;
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
            'is_featured' => ['boolean'],
            // Batas paket; kosong = tanpa batas. Key events/pages = id kategori page (sudah disaring di prepareForValidation).
            'limits' => ['array'],
            'limits.events' => ['array'],
            'limits.events.*' => ['integer', 'min:0', 'max:1000'],
            'limits.pages' => ['array'],
            'limits.pages.*' => ['integer', 'min:0', 'max:1000'],
            'limits.invoices.monthly' => ['nullable', 'integer', 'min:0', 'max:'.PlanLimits::MAX_VALUE],
            'limits.whatsapp.monthly' => ['nullable', 'integer', 'min:0', 'max:'.PlanLimits::MAX_VALUE],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_trial' => $this->boolean('is_trial'), 'is_featured' => $this->boolean('is_featured')]);

        // Isian kosong dibuang (= tanpa batas); kategori yang tidak dikenal / bukan milik layanannya diabaikan.
        // Event = kategori bertemplate tiket, Pages = kategori lain.
        $limits = (array) $this->input('limits');
        $types = fn (string $app) => array_filter((array) ($limits[$app] ?? []), 'filled');
        $known = fn (array $values, string $app) => array_intersect_key($values, array_flip(
            PageType::whereKey(array_keys($values))->get(['id', 'template'])
                ->filter(fn ($type) => Apps::forTemplate($type->template) === $app)->pluck('id')->all()
        ));
        $this->merge(['limits' => [
            'events' => $known($types(Apps::EVENTS), Apps::EVENTS),
            'pages' => $known($types(Apps::PAGES), Apps::PAGES),
            'invoices' => array_filter(['monthly' => $limits['invoices']['monthly'] ?? null], 'filled'),
            'whatsapp' => array_filter(['monthly' => $limits['whatsapp']['monthly'] ?? null], 'filled'),
        ]]);
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
            'limits.events.*' => 'batas event',
            'limits.pages.*' => 'batas page',
            'limits.invoices.monthly' => 'batas invoice per bulan',
            'limits.whatsapp.monthly' => 'batas pesan WhatsApp per bulan',
        ];
    }
}
