{{-- Kartu e-ticket: dipakai halaman e-ticket & preview desain. --}}
<div class="rounded-4 overflow-hidden shadow-sm border bg-white mx-auto" style="max-width: 560px;">
    <div data-eticket-bg class="d-flex align-items-end p-3"
        style="min-height: 180px; background: #1f2937 center / cover no-repeat; {{ $background ? "background-image: url('".e($background)."');" : '' }}">
        <div class="bg-white bg-opacity-75 rounded-3 px-3 py-2">
            <div class="fw-semibold">{{ $eventName }}</div>
            @if ($eventDate) <div class="small text-muted">{{ $eventDate }}</div> @endif
        </div>
    </div>
    <div class="p-3 p-md-4 d-flex gap-3 align-items-center flex-wrap flex-sm-nowrap">
        <div class="flex-grow-1">
            <span class="badge bg-dark mb-2">{{ $category }}</span>
            <div class="small text-muted">Nama</div>
            <div class="fw-semibold mb-2">{{ $holder }}</div>
            <div class="row g-2 small">
                <div class="col-6"><div class="text-muted">Kode Booking</div><div class="fs-5 fw-semibold">{{ $booking }}</div></div>
                <div class="col-6"><div class="text-muted">No. Tiket</div><div class="fw-semibold">{{ $number }}</div></div>
            </div>
            <div class="small text-muted mt-2">Tiket {{ $sequence }}</div>
        </div>
        <div class="text-center mx-auto">
            @if ($qr)
                <img src="{{ $qr }}" alt="QR e-ticket" width="160" height="160">
            @else
                <div class="border rounded d-flex align-items-center justify-content-center text-muted" style="width: 160px; height: 160px;"><i class="ri-qr-code-line fs-1"></i></div>
            @endif
        </div>
    </div>
</div>
