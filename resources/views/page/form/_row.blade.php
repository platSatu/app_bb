{{-- Satu baris field di form builder. $i = indeks, $row = data baris. --}}
@php
    $err = fn ($key) => $errors->first("fields.{$i}.{$key}");
    $options = is_array($row['options'] ?? null) ? implode("\n", $row['options']) : ($row['options_text'] ?? '');
@endphp
<div class="border rounded-3 p-3 mb-3 bg-white shadow-sm" data-row>
    <input type="hidden" name="fields[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="badge bg-light text-dark" data-row-number>#</span>
        @if ($canManage)
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" data-move="up" title="Naik"><i class="ri-arrow-up-s-line"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-move="down" title="Turun"><i class="ri-arrow-down-s-line"></i></button>
                <button type="button" class="btn btn-outline-danger" data-remove title="Hapus baris"><i class="ri-delete-bin-line"></i></button>
            </div>
        @endif
    </div>
    <div class="row g-2">
        <div class="col-md-5">
            <label class="form-label small mb-1">Jenis Field</label>
            <select name="fields[{{ $i }}][type]" class="form-select form-select-sm @if ($err('type')) is-invalid @endif" data-type>
                @foreach (\App\Models\PageFormField::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected(($row['type'] ?? 'text') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-7">
            <label class="form-label small mb-1">Label</label>
            <input type="text" name="fields[{{ $i }}][label]" maxlength="150" value="{{ $row['label'] ?? '' }}"
                class="form-control form-control-sm @if ($err('label')) is-invalid @endif" placeholder="mis. Nama Lengkap" data-label>
            @if ($err('label')) <div class="invalid-feedback">{{ $err('label') }}</div> @endif
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Placeholder (opsional)</label>
            <input type="text" name="fields[{{ $i }}][placeholder]" maxlength="150" value="{{ $row['placeholder'] ?? '' }}" class="form-control form-control-sm" data-placeholder>
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Keterangan (opsional)</label>
            <input type="text" name="fields[{{ $i }}][help_text]" maxlength="255" value="{{ $row['help_text'] ?? '' }}" class="form-control form-control-sm" data-help>
        </div>
        <div class="col-12" data-options-box>
            <label class="form-label small mb-1">Pilihan (satu per baris)</label>
            <textarea name="fields[{{ $i }}][options_text]" rows="3" class="form-control form-control-sm @if ($err('options')) is-invalid @endif"
                placeholder="Kelas Piano&#10;Kelas Gitar" data-options>{{ $options }}</textarea>
            @if ($err('options')) <div class="invalid-feedback">{{ $err('options') }}</div> @endif
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Simpan ke Kontak sebagai</label>
            <select name="fields[{{ $i }}][contact_field]" class="form-select form-select-sm @if ($err('contact_field')) is-invalid @endif">
                <option value="">— Tidak —</option>
                @foreach (\App\Models\PageFormField::CONTACT_FIELDS as $value => [$label])
                    <option value="{{ $value }}" @selected(($row['contact_field'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('contact_field')) <div class="invalid-feedback">{{ $err('contact_field') }}</div> @endif
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch mb-1">
                <input type="hidden" name="fields[{{ $i }}][is_required]" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="req-{{ $i }}" name="fields[{{ $i }}][is_required]" value="1"
                    @checked(filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN)) data-required>
                <label class="form-check-label small" for="req-{{ $i }}">Wajib diisi</label>
            </div>
        </div>
    </div>
</div>
