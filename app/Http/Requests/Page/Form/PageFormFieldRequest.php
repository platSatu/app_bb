<?php

namespace App\Http\Requests\Page\Form;

use App\Models\PageFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageFormFieldRequest extends FormRequest
{
    /** Pilihan ditulis satu per baris di textarea, disimpan sebagai array. */
    protected function prepareForValidation(): void
    {
        $lines = preg_split('/\R/', (string) $this->input('options_text'));

        $this->merge([
            'options' => in_array($this->input('type'), PageFormField::WITH_OPTIONS, true)
                ? array_values(array_unique(array_filter(array_map('trim', $lines), 'strlen')))
                : null,
            'is_required' => $this->boolean('is_required'),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(PageFormField::TYPES))],
            'label' => ['required', 'string', 'max:150'],
            'placeholder' => ['nullable', 'string', 'max:150'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'options' => [Rule::requiredIf(in_array($this->input('type'), PageFormField::WITH_OPTIONS, true)), 'nullable', 'array', 'max:50'],
            'options.*' => ['string', 'max:100'],
            'is_required' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['options.required' => 'Isi minimal satu pilihan, satu pilihan per baris.'];
    }

    public function attributes(): array
    {
        return ['type' => 'jenis field', 'label' => 'label', 'help_text' => 'keterangan', 'options' => 'pilihan'];
    }

    public function fieldData(): array
    {
        return $this->safe()->only(['type', 'label', 'placeholder', 'help_text', 'options', 'is_required']);
    }
}
