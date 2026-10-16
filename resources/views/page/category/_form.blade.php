<div class="row g-3">
    <div class="col-md-6">
        <label for="page_type_id" class="form-label">Kategori Page</label>
        <select id="page_type_id" name="page_type_id" class="form-select @error('page_type_id') is-invalid @enderror" required>
            <option value="">-- Pilih kategori --</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected(old('page_type_id', $page->page_type_id) === $type->id)>
                    {{ $type->name }}{{ $type->isActive() ? '' : ' (tidak aktif)' }}
                </option>
            @endforeach
        </select>
        @error('page_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nama Page</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $page->name) }}" placeholder="mis. Pendaftaran Seminar Oktober" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="branch_id" class="form-label">Branch</label>
        <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $page->branch_id) === $branch->id)>
                    {{ $branch->relationLoaded('company') && $branch->company ? $branch->company->name.' — ' : '' }}{{ $branch->name }}{{ $branch->isActive() ? '' : ' (tidak aktif)' }}
                </option>
            @endforeach
        </select>
        @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\PageCategory::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $page->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="slug" class="form-label">Link</label>
        <div class="input-group">
            <span class="input-group-text">{{ url('page') }}/</span>
            <input type="text" id="slug" name="slug" maxlength="100" class="form-control @error('slug') is-invalid @enderror"
                value="{{ old('slug', $page->slug) }}" placeholder="otomatis dari nama page">
        </div>
        <div class="form-text">
            Huruf kecil, angka, dan tanda minus saja.
            @if ($page->exists)
                <span class="text-warning">Kalau link diganti, QR code lama yang sudah dicetak tidak bisa dipakai lagi.</span>
            @endif
        </div>
        @error('slug') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="start_date" class="form-label">Tanggal Mulai (opsional)</label>
        <input type="date" id="start_date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $page->start_date?->format('Y-m-d')) }}">
        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="end_date" class="form-label">Tanggal Selesai (opsional)</label>
        <input type="date" id="end_date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $page->end_date?->format('Y-m-d')) }}">
        <div class="form-text">Kosongkan kalau page berlaku terus.</div>
        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi (opsional)</label>
        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $page->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route($routes.'.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
