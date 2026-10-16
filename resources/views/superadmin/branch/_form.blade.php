<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label for="company_id" class="form-label">Perusahaan</label>
        @if ($branch->exists)
            <input type="text" class="form-control" value="{{ $branch->company?->name }}" disabled>
            <div class="form-text">Perusahaan tidak bisa dipindah setelah branch dibuat.</div>
        @else
            <select id="company_id" name="company_id" class="form-select @error('company_id') is-invalid @enderror" required>
                <option value="">-- Pilih perusahaan --</option>
                @foreach ($companies as $option)
                    <option value="{{ $option->id }}" @selected(old('company_id', $selectedCompany ?? null) === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
            @error('company_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>
</div>

@include('settings.branch._fields')

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('superadmin.branches.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
