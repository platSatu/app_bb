@php($withOptions = \App\Models\PageFormField::WITH_OPTIONS)
<div class="row g-3" data-field-form>
    <div class="col-md-6">
        <label class="form-label" for="type">Jenis Field</label>
        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" data-type-select required>
            @foreach (\App\Models\PageFormField::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $field->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="label">Label</label>
        <input type="text" id="label" name="label" maxlength="150" class="form-control @error('label') is-invalid @enderror"
            value="{{ old('label', $field->label) }}" placeholder="mis. Nama Lengkap" required>
        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="placeholder">Placeholder (opsional)</label>
        <input type="text" id="placeholder" name="placeholder" maxlength="150" class="form-control @error('placeholder') is-invalid @enderror"
            value="{{ old('placeholder', $field->placeholder) }}" placeholder="Contoh isian di dalam kotak">
        @error('placeholder') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="help_text">Keterangan (opsional)</label>
        <input type="text" id="help_text" name="help_text" maxlength="255" class="form-control @error('help_text') is-invalid @enderror"
            value="{{ old('help_text', $field->help_text) }}" placeholder="Teks kecil di bawah field">
        @error('help_text') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12" data-options-box @if (! in_array(old('type', $field->type), $withOptions, true)) hidden @endif>
        <label class="form-label" for="options_text">Pilihan</label>
        <textarea id="options_text" name="options_text" rows="4" class="form-control @error('options') is-invalid @enderror"
            placeholder="Satu pilihan per baris, mis.&#10;Kelas Piano&#10;Kelas Gitar&#10;Kelas Vokal">{{ old('options_text', implode("\n", $field->options ?? [])) }}</textarea>
        @error('options') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @error('options.*') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_required" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_required" name="is_required" value="1" @checked(old('is_required', $field->is_required))>
            <label class="form-check-label" for="is_required">Wajib diisi</label>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-field-form]').forEach(function (form) {
        var select = form.querySelector('[data-type-select]');
        var box = form.querySelector('[data-options-box]');
        var withOptions = @json($withOptions);
        select.addEventListener('change', function () { box.hidden = withOptions.indexOf(select.value) === -1; });
    });
</script>
