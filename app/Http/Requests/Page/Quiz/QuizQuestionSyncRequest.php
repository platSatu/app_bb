<?php

namespace App\Http\Requests\Page\Quiz;

use App\Models\QuizQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Semua soal dikirim sekaligus; urutan baris = urutan soal. */
class QuizQuestionSyncRequest extends FormRequest
{
    public const MAX_QUESTIONS = 100;
    public const MAX_OPTIONS = 10;

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('questions', []))->values()->map(function ($row) {
            $row = is_array($row) ? $row : [];
            $type = $row['type'] ?? null;
            $correct = (string) ($row['correct'] ?? '');

            // Penanda benar dibaca dari radio sebelum indeks pilihan dirapikan.
            $options = collect($row['options'] ?? [])
                ->map(fn ($option, $key) => [
                    'id' => $option['id'] ?? null,
                    'label' => trim((string) ($option['label'] ?? '')),
                    'is_correct' => (string) $key === $correct,
                ])
                ->values()
                ->all();

            return [
                'id' => $row['id'] ?? null,
                'type' => $type,
                'question' => trim((string) ($row['question'] ?? '')),
                'points' => $type === QuizQuestion::TYPE_TEXTAREA ? 0 : ($row['points'] ?? 10),
                'status' => $row['status'] ?? 'active',
                'answer_keys' => $type === QuizQuestion::TYPE_TEXT
                    ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($row['answer_keys_text'] ?? ''))), 'strlen')))
                    : null,
                'options' => $type === QuizQuestion::TYPE_SINGLE ? $options : [],
            ];
        });

        $this->merge(['questions' => $rows->all()]);
    }

    public function rules(): array
    {
        return [
            'questions' => ['present', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*.id' => ['nullable', 'uuid'],
            'questions.*.type' => ['required', Rule::in(array_keys(QuizQuestion::TYPES))],
            'questions.*.question' => ['required', 'string', 'max:2000'],
            'questions.*.points' => ['required', 'integer', 'min:0', 'max:1000'],
            'questions.*.status' => ['required', Rule::in(array_keys(QuizQuestion::STATUS_LABELS))],
            'questions.*.answer_keys' => ['nullable', 'array', 'max:20'],
            'questions.*.answer_keys.*' => ['string', 'max:255'],
            'questions.*.options' => ['array', 'max:'.self::MAX_OPTIONS],
            'questions.*.options.*.id' => ['nullable', 'uuid'],
            'questions.*.options.*.label' => ['required', 'string', 'max:255'],
            // Wajib ada aturan: validated() hanya mengembalikan field yang punya aturan.
            'questions.*.options.*.is_correct' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('questions', []) as $i => $row) {
                $label = 'Soal '.($i + 1).': ';

                if ($row['type'] === QuizQuestion::TYPE_SINGLE) {
                    if (count($row['options']) < 2) {
                        $validator->errors()->add("questions.{$i}.options", $label.'buat minimal 2 pilihan jawaban.');
                    } elseif (collect($row['options'])->where('is_correct', true)->count() !== 1) {
                        $validator->errors()->add("questions.{$i}.options", $label.'tandai satu jawaban yang benar.');
                    }
                }

                if ($row['type'] === QuizQuestion::TYPE_TEXT && empty($row['answer_keys'])) {
                    $validator->errors()->add("questions.{$i}.answer_keys", $label.'isi minimal satu kunci jawaban.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'questions.max' => 'Satu quiz maksimal '.self::MAX_QUESTIONS.' soal.',
            'questions.*.question.required' => 'Pertanyaan wajib diisi di setiap soal.',
            'questions.*.options.*.label.required' => 'Teks pilihan jawaban tidak boleh kosong.',
            'questions.*.options.max' => 'Maksimal '.self::MAX_OPTIONS.' pilihan per soal.',
        ];
    }
}
