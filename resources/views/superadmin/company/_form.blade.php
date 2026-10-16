<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="user_id" class="form-label">Pemilik</label>
        @if ($company->exists)
            <input type="text" class="form-control" value="{{ $company->user?->name }} ({{ $company->user?->email }})" disabled>
            <div class="form-text">Pemilik tidak bisa dipindah setelah perusahaan dibuat.</div>
        @else
            <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                <option value="">-- Pilih user --</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected(old('user_id') === $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                @endforeach
            </select>
            <div class="form-text">Hanya user yang belum punya perusahaan.</div>
            @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>

    <div class="col-md-3">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="{{ \App\Models\UserCompany::STATUS_ACTIVE }}" @selected(old('status', $company->status) === \App\Models\UserCompany::STATUS_ACTIVE)>Aktif</option>
            <option value="{{ \App\Models\UserCompany::STATUS_INACTIVE }}" @selected(old('status', $company->status) === \App\Models\UserCompany::STATUS_INACTIVE)>Tidak Aktif</option>
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label for="limit" class="form-label">Limit</label>
        <input type="number" min="0" id="limit" name="limit" class="form-control @error('limit') is-invalid @enderror"
            value="{{ old('limit', $company->limit) }}">
        @error('limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@include('settings.company._fields')

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('superadmin.companies.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
