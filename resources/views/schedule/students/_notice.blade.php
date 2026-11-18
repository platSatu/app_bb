{{-- Hasil Import Excel: baris yang dilewati beserta alasannya, supaya file bisa diperbaiki lalu diunggah ulang. --}}
@if ($skipped = session('import_skipped'))
    <div class="alert alert-warning alert-dismissible fade show small" role="alert">
        <div class="fw-semibold mb-1"><i class="ri-error-warning-line me-1"></i>{{ count($skipped) }} baris dilewati saat import:</div>
        <ul class="mb-0 ps-3" style="max-height: 14rem; overflow-y: auto">
            @foreach ($skipped as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
@if ($errors->has('file') || $errors->has('branch_id'))
    <div class="alert alert-danger small">{{ $errors->first('file') ?: $errors->first('branch_id') }}</div>
@endif
