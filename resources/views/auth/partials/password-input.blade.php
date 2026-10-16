<div class="mb-4">
    <div class="position-relative">
        <input type="password" class="form-control @error($name) is-invalid @enderror" id="{{ $name }}"
            name="{{ $name }}" placeholder="{{ $placeholder }}" required autocomplete="{{ $autocomplete }}">
        <button type="button" class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted toggle-password"
            data-target="{{ $name }}" aria-label="Tampilkan password">
            <i class="ri-eye-off-line align-middle"></i>
        </button>
    </div>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
