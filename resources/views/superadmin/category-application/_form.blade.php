<div class="d-flex align-items-center gap-3 mb-4">
    <div class="h-80px w-80px rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
        <img src="{{ $category->imageUrl() }}" alt="" id="image-preview" class="w-100 h-100 {{ $category->imageUrl() ? '' : 'd-none' }}" style="object-fit: contain;">
        @unless ($category->imageUrl())
            <i class="ri-apps-line fs-2 text-muted" id="image-placeholder"></i>
        @endunless
    </div>
    <div>
        <label for="image" class="form-label mb-1">Gambar (opsional)</label>
        <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp" class="form-control @error('image') is-invalid @enderror">
        <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB. Otomatis diperkecil supaya ringan.</div>
        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <label for="name" class="form-label">Nama</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if ($category->slug)
            <div class="form-text">Slug: <code>{{ $category->slug }}</code></div>
        @endif
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\CategoryApplication::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $category->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi (opsional)</label>
        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route($indexRoute ?? 'superadmin.category-applications.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>

<script>
    document.getElementById('image').addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (!file) return;
        var preview = document.getElementById('image-preview');
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
        var placeholder = document.getElementById('image-placeholder');
        if (placeholder) placeholder.remove();
    });
</script>
