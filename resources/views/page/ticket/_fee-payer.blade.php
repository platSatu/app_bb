{{--
    Jenis event tiket (penanggung biaya layanan / event gratis), hanya tampil kalau kategori page bertemplate "ticket".
    Membuat page tiket baru selalu lewat halaman simulasi dulu (pages.ticket-fee) untuk owner.
--}}
@php
    $ticketSetting = \App\Models\TicketSetting::current();
    $feePercent = rtrim(rtrim(number_format((float) $ticketSetting->fee_percent, 2, ',', '.'), '0'), ',').'%';
    $freeLimit = $page->exists && $page->setting('ticket.free_unlimited') ? null : $ticketSetting->free_allowance;
    $feeOptions = [
        \App\Models\TicketSetting::PAYER_ORGANIZER => ['Ditanggung penyelenggara', "Tiket berbayar. Biaya {$feePercent} dipotong dari hasil penjualan."],
        \App\Models\TicketSetting::PAYER_BUYER => ['Ditanggung pembeli', "Tiket berbayar. Biaya {$feePercent} ditambahkan saat pembeli checkout."],
        \App\Models\TicketSetting::PAYER_FREE => ['Event gratis', 'Semua tiket Rp0'.($freeLimit ? ', total maksimal '.number_format($freeLimit, 0, ',', '.').' tiket.' : ', tanpa batas.')],
    ];
    $feePayer = old('ticket_fee_payer', request('fee_payer') ?: ($page->exists ? $page->ticketFeePayer() : null));
    $feeLocked = $page->isTemplate(\App\Models\PageType::TEMPLATE_TICKET) && $page->ticketFeeLocked();
    $simulation = $routes === 'pages' ? route('pages.ticket-fee') : null;
@endphp
<div class="col-12" data-ticket-fee @if (! $page->isTemplate(\App\Models\PageType::TEMPLATE_TICKET)) hidden @endif data-simulation="{{ $page->exists || filled(request('fee_payer')) ? '' : $simulation }}">
    <div class="border rounded-3 p-3 bg-light">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <div class="fw-semibold">Jenis event</div>
            @if ($simulation)
                <a href="{{ $simulation }}" target="_blank" rel="noopener" class="small">Lihat simulasi pendapatan <i class="ri-external-link-line"></i></a>
            @endif
        </div>
        @if ($feeLocked)
            <input type="hidden" name="ticket_fee_payer" value="{{ $page->ticketFeePayer() }}">
            <div class="small">{{ $feeOptions[$page->ticketFeePayer()][0] }}: {{ $feeOptions[$page->ticketFeePayer()][1] }} <span class="text-muted">Tidak bisa diganti karena sudah ada pesanan.</span></div>
        @else
            <div class="row g-2">
                @foreach ($feeOptions as $value => [$label, $hint])
                    <div class="col-md-4">
                        <label class="d-flex gap-2 border rounded-3 bg-white p-2 h-100" for="fee_payer_{{ $value }}" style="cursor:pointer">
                            <input class="form-check-input mt-1 flex-shrink-0" type="radio" name="ticket_fee_payer" id="fee_payer_{{ $value }}" value="{{ $value }}" @checked($feePayer === $value)>
                            <span><span class="fw-semibold d-block small">{{ $label }}</span><span class="small text-muted">{{ $hint }}</span></span>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Bisa diganti selama belum ada pesanan.</div>
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
