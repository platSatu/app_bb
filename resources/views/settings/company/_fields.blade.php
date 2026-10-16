<div class="d-flex align-items-center gap-3 mb-4">
    <div class="h-80px w-80px rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
        @if ($company->logoUrl())
            <img src="{{ $company->logoUrl() }}" alt="Logo {{ $company->name }}" id="logo-preview" class="w-100 h-100" style="object-fit: contain;">
        @else
            <img src="" alt="" id="logo-preview" class="w-100 h-100 d-none" style="object-fit: contain;">
            <i class="ri-building-line fs-2 text-muted" id="logo-placeholder"></i>
        @endif
    </div>
    <div>
        <label for="logo" class="form-label mb-1">Logo Perusahaan</label>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
            class="form-control @error('logo') is-invalid @enderror">
        <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB. Otomatis diperkecil supaya ringan.</div>
        @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama Perusahaan</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $company->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if ($company->slug)
            <div class="form-text">Alamat singkat: <code>{{ $company->slug }}</code></div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email Perusahaan</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $company->email) }}">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="phone" class="form-label">Telepon</label>
        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $company->phone) }}" placeholder="021-1234567">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="fax" class="form-label">Fax</label>
        <input type="text" id="fax" name="fax" class="form-control @error('fax') is-invalid @enderror"
            value="{{ old('fax', $company->fax) }}">
        @error('fax') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="handphone" class="form-label">WhatsApp</label>
        <div class="input-group">
            <span class="input-group-text">+62</span>
            <input type="text" inputmode="numeric" id="handphone" name="handphone"
                class="form-control @error('handphone') is-invalid @enderror"
                value="{{ preg_replace('/^62/', '', (string) old('handphone', $company->handphone)) }}" placeholder="81234567890">
        </div>
        @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="4"
            class="form-control @error('description') is-invalid @enderror">{{ old('description', $company->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<script>
    document.getElementById('logo').addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (!file) return;
        var preview = document.getElementById('logo-preview');
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
        var placeholder = document.getElementById('logo-placeholder');
        if (placeholder) placeholder.remove();
    });
</script>
