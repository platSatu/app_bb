<?php

namespace App\Http\Requests\Page\Landing;

use App\Models\LandingSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LandingSectionRequest extends FormRequest
{
    public const MAX_SECTIONS = 30;

    public function rules(): array
    {
        return [
            'sections' => ['present', 'array', 'max:'.self::MAX_SECTIONS],
            'sections.*.id' => ['nullable', 'uuid'],
            'sections.*.type' => ['required', Rule::in(array_keys(LandingSection::TYPES))],
            'sections.*.status' => ['required', Rule::in(array_keys(LandingSection::STATUS_LABELS))],
            'sections.*.data' => ['nullable', 'array'],
            'sections.*.data.*' => ['nullable', 'string', 'max:4000'],
            'sections.*.button_page_id' => ['nullable', 'uuid'],
            'sections.*.button_url' => ['nullable', 'string', 'max:500'],
            'sections.*.image' => ['nullable', 'string', 'max:255'],
            'sections.*.remove_image' => ['nullable', 'boolean'],
            'sections.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'sections.*.images' => ['nullable', 'array', 'max:'.LandingSection::MAX_GALLERY],
            'sections.*.images.*' => ['string', 'max:255'],
            'sections.*.image_files' => ['nullable', 'array', 'max:'.LandingSection::MAX_GALLERY],
            'sections.*.image_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.*.image_file.max' => 'Ukuran gambar maksimal 5 MB.',
            'sections.*.image_files.*.max' => 'Ukuran gambar maksimal 5 MB.',
            'sections.max' => 'Maksimal '.self::MAX_SECTIONS.' section per landing page.',
        ];
    }

    /** Baris form + file uploadnya, urutan sesuai di layar. */
    public function rows(): array
    {
        return collect($this->validated('sections') ?? [])
            ->map(fn ($row, $key) => [
                ...$row,
                'image_file' => $this->file("sections.{$key}.image_file"),
                'image_files' => $this->file("sections.{$key}.image_files") ?? [],
            ])
            ->values()
            ->all();
    }
}
