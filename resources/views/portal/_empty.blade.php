<div class="text-center text-muted py-5">
    <i class="{{ $icon ?? 'ri-calendar-line' }} fs-1 d-block mb-2 opacity-50"></i>
    <div class="fw-semibold text-body">{{ $title }}</div>
    @isset($text) <div class="small mt-1">{{ $text }}</div> @endisset
</div>
