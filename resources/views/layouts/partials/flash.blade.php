@foreach (['success' => ['success', 'ri-checkbox-circle-line'], 'error' => ['danger', 'ri-error-warning-line'], 'warning' => ['warning', 'ri-alert-line'], 'info' => ['info', 'ri-information-line']] as $key => [$color, $icon])
    @if (session($key))
        <div class="alert alert-{{ $color }} alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="{{ $icon }} fs-5"></i>
            <div class="flex-grow-1">{{ session($key) }}</div>
            @if ($key === 'error' && is_array(session('error_action')))
                <a href="{{ session('error_action')['url'] ?? '#' }}" class="btn btn-sm btn-danger text-nowrap">{{ session('error_action')['label'] ?? 'Lihat paket' }}</a>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
@endforeach


{{-- Tombol kuota tanpa pesan error (mis. Salin ke bulan depan: sebagian jadwal dilewati karena kuota murid penuh). --}}
@if (is_array(session('error_action')) && ! session('error'))
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="ri-alert-line fs-5"></i>
        <div class="flex-grow-1">Ada yang belum tersimpan karena kuota paket Anda sudah penuh.</div>
        <a href="{{ session('error_action')['url'] ?? '#' }}" class="btn btn-sm btn-warning text-nowrap">{{ session('error_action')['label'] ?? 'Lihat paket' }}</a>
    </div>
@endif
