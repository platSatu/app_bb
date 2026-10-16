@foreach (['success' => ['success', 'ri-checkbox-circle-line'], 'error' => ['danger', 'ri-error-warning-line'], 'warning' => ['warning', 'ri-alert-line'], 'info' => ['info', 'ri-information-line']] as $key => [$color, $icon])
    @if (session($key))
        <div class="alert alert-{{ $color }} alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="{{ $icon }} fs-5"></i>
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
@endforeach

