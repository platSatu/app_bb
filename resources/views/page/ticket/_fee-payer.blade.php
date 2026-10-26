{{--
    Penanggung biaya layanan tiket, hanya tampil kalau kategori page bertemplate "ticket".
    Membuat page tiket baru selalu lewat halaman simulasi dulu (pages.ticket-fee) untuk owner.
--}}
@php
    $feePercent = rtrim(rtrim(number_format((float) \App\Models\TicketSetting::current()->fee_percent, 2, ',', '.'), '0'), ',').'%';
    $feePayer = old('ticket_fee_payer', request('fee_payer') ?: ($page->exists ? $page->ticketFeePayer() : null));
    $feeLocked = $page->isTemplate(\App\Models\PageType::TEMPLATE_TICKET) && $page->ticketFeeLocked();
    $simulation = $routes === 'pages' ? route('pages.ticket-fee') : null;
@endphp
<div class="col-12" data-ticket-fee @if (! $page->isTemplate(\App\Models\PageType::TEMPLATE_TICKET)) hidden @endif data-simulation="{{ $page->exists || filled(request('fee_payer')) ? '' : $simulation }}">
    <div class="border rounded-3 p-3 bg-light">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <div class="fw-semibold">Biaya layanan tiket: {{ $feePercent }} dari harga tiket berbayar</div>
            @if ($simulation)
                <a href="{{ $simulation }}" target="_blank" rel="noopener" class="small">Lihat simulasi pendapatan <i class="ri-external-link-line"></i></a>
            @endif
        </div>
        @if ($feeLocked)
            <input type="hidden" name="ticket_fee_payer" value="{{ $page->ticketFeePayer() }}">
            <div class="small">{{ \App\Models\TicketSetting::PAYER_LABELS[$page->ticketFeePayer()] }}. <span class="text-muted">Tidak bisa diganti karena tiket berbayar sudah ada yang terjual.</span></div>
        @else
            <div class="d-flex flex-wrap gap-3">
                @foreach (\App\Models\TicketSetting::PAYER_LABELS as $value => $label)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ticket_fee_payer" id="fee_payer_{{ $value }}" value="{{ $value }}" @checked($feePayer === $value)>
                        <label class="form-check-label" for="fee_payer_{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Ditanggung penyelenggara: dipotong dari hasil penjualan. Ditanggung pembeli: ditambahkan saat checkout.</div>
        @endif
        @error('ticket_fee_payer') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('page_type_id');
            var box = document.querySelector('[data-ticket-fee]');
            if (!select || !box) return;
            var sync = function (navigate) {
                var option = select.options[select.selectedIndex];
                var isTicket = option && option.dataset.template === 'ticket';
                // Page tiket baru: tampilkan simulasi & penjelasan biaya dulu, baru isi form.
                if (navigate && isTicket && box.dataset.simulation) {
                    window.location = box.dataset.simulation + '?type=' + encodeURIComponent(option.value);
                    return;
                }
                box.hidden = !isTicket;
            };
            select.addEventListener('change', function () { sync(true); });
            sync(false);
        })();
    </script>
@endpush
