{{-- Satu soal. $i = indeks soal, $row = data soal (termasuk options). --}}
@php
    $err = fn ($key) => $errors->first("questions.{$i}.{$key}");
    $type = $row['type'] ?? \App\Models\QuizQuestion::TYPE_SINGLE;
    $options = $row['options'] ?? [];
    $keys = is_array($row['answer_keys'] ?? null) ? implode("\n", $row['answer_keys']) : '';
@endphp
<div class="bg-white border rounded-3 shadow-sm p-3 mb-3" data-row data-index="{{ $i }}">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
        <span class="badge bg-light text-dark" data-row-number>#</span>
        <select name="questions[{{ $i }}][type]" class="form-select form-select-sm" style="max-width: 220px;" data-type>
            @foreach (\App\Models\QuizQuestion::TYPES as $value => $label)
                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="input-group input-group-sm ms-auto" style="max-width: 130px;" data-points-box>
            <input type="number" name="questions[{{ $i }}][points]" min="0" max="1000" value="{{ $row['points'] ?? 10 }}" class="form-control">
            <span class="input-group-text">poin</span>
        </div>
        <select name="questions[{{ $i }}][status]" class="form-select form-select-sm" style="max-width: 130px;">
            @foreach (\App\Models\QuizQuestion::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($row['status'] ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus soal"><i class="ri-delete-bin-line"></i></button>
    </div>
    <input type="hidden" name="questions[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <textarea name="questions[{{ $i }}][question]" rows="2" maxlength="2000" class="form-control form-control-sm mb-2 @if ($err('question')) is-invalid @endif" placeholder="Tulis pertanyaan">{{ $row['question'] ?? '' }}</textarea>
    @if ($err('question')) <div class="invalid-feedback d-block mb-2">{{ $err('question') }}</div> @endif

    <div data-box="single">
        <div class="small text-muted mb-1">Pilihan jawaban — klik <i class="ri-checkbox-circle-line"></i> pada jawaban yang benar</div>
        <div data-options>
            @foreach ($options as $j => $option)
                @include('page.quiz._option-row', ['i' => $i, 'j' => $j, 'option' => $option])
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-link px-0" data-add-option><i class="ri-add-line"></i> Tambah pilihan</button>
        @if ($err('options')) <div class="text-danger fs-13">{{ $err('options') }}</div> @endif
    </div>

    <div data-box="text">
        <label class="small text-muted mb-1">Kunci jawaban (satu per baris; huruf besar/kecil diabaikan)</label>
        <textarea name="questions[{{ $i }}][answer_keys_text]" rows="2" class="form-control form-control-sm @if ($err('answer_keys')) is-invalid @endif" placeholder="Jakarta&#10;DKI Jakarta">{{ $keys }}</textarea>
        @if ($err('answer_keys')) <div class="invalid-feedback">{{ $err('answer_keys') }}</div> @endif
    </div>

    <div data-box="textarea" class="small text-muted"><i class="ri-information-line"></i> Jawaban dikumpulkan tanpa poin, bisa dibaca di tab Hasil.</div>
</div>
