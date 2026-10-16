<?php

namespace App\Http\Requests\Page\Form;

use App\Models\PageFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Semua baris form builder dikirim sekaligus; urutan baris = urutan field. */
class PageFormSyncRequest extends FormRequest
{
    public const MAX_FIELDS = 50;

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('fields', []))->values()->map(function ($row) {
            $row = is_array($row) ? $row : [];
            $lines = preg_split('/\R/', (string) ($row['options_text'] ?? ''));

            return [
                'id' => $row['id'] ?? null,
                'type' => $row['type'] ?? null,
                'label' => isset($row['label']) ? trim((string) $row['label']) : null,
                'placeholder' => $row['placeholder'] ?? null,
                'help_text' => $row['help_text'] ?? null,
                'options' => in_array($row['type'] ?? null, PageFormField::WITH_OPTIONS, true)
                    ? array_values(array_unique(array_filter(array_map('trim', $lines), 'strlen')))
                    : null,
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'contact_field' => filled($row['contact_field'] ?? null) ? $row['contact_field'] : null,
            ];
        });

        $this->merge(['fields' => $rows->all()]);
    }

    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:'.self::MAX_FIELDS],
            'fields.*.id' => ['nullable', 'uuid'],
            'fields.*.type' => ['required', Rule::in(array_keys(PageFormField::TYPES))],
            'fields.*.label' => ['required', 'string', 'max:150'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:150'],
            'fields.*.help_text' => ['nullable', 'string', 'max:255'],
            'fields.*.options' => ['nullable', 'array', 'max:50'],
            'fields.*.options.*' => ['string', 'max:100'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.contact_field' => ['nullable', Rule::in(array_keys(PageFormField::CONTACT_FIELDS)), 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('fields', []) as $i => $row) {
                if (in_array($row['type'], PageFormField::WITH_OPTIONS, true) && empty($row['options'])) {
                    $validator->errors()->add("fields.{$i}.options", 'Baris '.($i + 1).': isi minimal satu pilihan.');
                }

                [$contactLabel, $allowedTypes] = PageFormField::CONTACT_FIELDS[$row['contact_field'] ?? ''] ?? [null, []];

                if ($contactLabel && ! in_array($row['type'], $allowedTypes, true)) {
                    $validator->errors()->add("fields.{$i}.contact_field", 'Baris '.($i + 1).": jenis field ini tidak cocok untuk disimpan sebagai {$contactLabel}.");
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'fields.max' => 'Satu form maksimal '.self::MAX_FIELDS.' field.',
            'fields.*.label.required' => 'Label wajib diisi di setiap baris.',
            'fields.*.contact_field.distinct' => 'Setiap jenis kontak (Nama/WhatsApp/Email) hanya boleh dipilih di satu baris.',
        ];
    }
}
