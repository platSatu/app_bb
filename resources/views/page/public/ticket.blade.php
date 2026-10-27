@php
    $rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
    // Hanya tampilan; jumlah yang ditagih tetap dihitung ulang di server (TicketOrderService).
    $feeBps = $page->ticketFeePayer() === \App\Models\TicketSetting::PAYER_BUYER ? (int) round((float) \App\Models\TicketSetting::current()->fee_percent * 100) : 0;
@endphp
@push('head')
    <style>
        .ticket-grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr; grid-template-areas: "buyer" "tickets" "summary"; }
        @media (min-width: 992px) {
            .ticket-grid { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); grid-template-areas: "buyer tickets" "buyer summary"; align-items: start; }
            .ticket-summary { position: sticky; top: 1.5rem; }
        }
        .ticket-grid .card { border: 0; border-radius: 1rem; box-shadow: 0 1px 3px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.04) !important; }
        .ticket-hero { border: 0; border-radius: 1.25rem; background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 16%, #fff), #fff 65%); }
        .ticket-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .75rem; border-radius: 999px; background: #fff; font-size: .875rem; color: #555; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
        .ticket-chip i { color: var(--brand); }
        /* Pilihan tiket bergaya karcis: pita warna di kiri, garis sobek putus-putus, dan lubang setengah lingkaran. */
        .ticket-option { position: relative; display: flex; align-items: stretch; margin-bottom: .75rem; border-radius: 14px; overflow: hidden; background: #fff; box-shadow: inset 0 0 0 1px #ebeaf0, 0 2px 6px rgba(16,24,40,.05); transition: box-shadow .15s, transform .15s; }
        .ticket-option::before { content: ''; flex: 0 0 8px; background: linear-gradient(180deg, var(--brand), color-mix(in srgb, var(--brand) 60%, #fff)); }
        .ticket-option.is-picked { box-shadow: inset 0 0 0 2px var(--brand), 0 6px 16px color-mix(in srgb, var(--brand) 18%, transparent); transform: translateY(-1px); }
        .ticket-option.is-off { opacity: .6; filter: grayscale(.6); }
        .karcis-body { flex: 1; min-width: 0; padding: .9rem 1rem; }
        .karcis-kicker { font-size: .65rem; letter-spacing: .25em; color: #9a98a6; font-weight: 600; }
        .karcis-price { font-size: 1.1rem; font-weight: 700; color: var(--brand); }
        .karcis-stub { position: relative; display: flex; align-items: center; padding: .9rem; border-left: 2px dashed #dddbe4; background: color-mix(in srgb, var(--brand) 4%, #fff); }
        .karcis-stub::before, .karcis-stub::after { content: ''; position: absolute; left: -9px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: inset 0 0 0 1px #ebeaf0; }
        .karcis-stub::before { top: -8px; }
        .karcis-stub::after { bottom: -8px; }
        /* Warna tombol ditulis langsung (tidak lewat variabel Bootstrap) supaya selalu terlihat. */
        .btn-order { background-color: var(--brand); border-color: var(--brand); color: #fff; font-weight: 600; }
        .btn-order:hover, .btn-order:focus { background-color: color-mix(in srgb, var(--brand) 88%, #000); border-color: color-mix(in srgb, var(--brand) 88%, #000); color: #fff; }
        .btn-order:disabled { background-color: color-mix(in srgb, var(--brand) 40%, #fff); border-color: transparent; color: #fff; opacity: 1; }
        .terms-dialog { border: 0; border-radius: 1rem; padding: 0; width: min(560px, calc(100% - 2rem)); box-shadow: 0 20px 60px rgba(16,24,40,.25); }
        .terms-dialog::backdrop { background: rgba(17,17,27,.45); }
        .qty { width: 116px; }
        .qty .btn { color: var(--brand); border-color: color-mix(in srgb, var(--brand) 35%, #fff); }
        .qty .btn:hover { background: color-mix(in srgb, var(--brand) 10%, #fff); }
        .qty .form-control { text-align: center; -moz-appearance: textfield; }
        .qty .form-control::-webkit-inner-spin-button, .qty .form-control::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .step-number { width: 26px; height: 26px; border-radius: 50%; background: var(--brand); color: #fff; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; }
    </style>
@endpush

@if ($page->start_date || $page->description)
    <div class="card ticket-hero shadow-sm mb-4">
        <div class="card-body p-4">
            @if ($page->start_date)
                <span class="ticket-chip"><i class="ri-calendar-event-line"></i>{{ $page->start_date->translatedFormat('l, d F Y') }}{{ $page->end_date && ! $page->end_date->isSameDay($page->start_date) ? ' – '.$page->end_date->translatedFormat('d F Y') : '' }}</span>
            @endif
            @if ($page->description)
                <p class="text-muted mb-0 {{ $page->start_date ? 'mt-3' : '' }}" style="white-space: pre-line;">{{ $page->description }}</p>
            @endif
        </div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
@endif

@if ($ticketCategories->isEmpty())
    <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 text-center text-muted">Tiket belum dibuka. Pantau terus halaman ini ya.</div></div>
@else
    <form method="POST" action="{{ route('page.tickets.checkout', $page->slug) }}" id="ticket-form" data-fee-bps="{{ $feeBps }}" enctype="multipart/form-data" onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf
        @include('page.public._honeypot')

        <div class="ticket-grid">
            <div class="card shadow-sm" style="grid-area: tickets;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">2</span> Pilih Tiket</h2>
                    @foreach ($ticketCategories as $category)
                        @php($reason = $category->unavailableReason())
                        <div class="ticket-option {{ $reason ? 'is-off' : '' }}" data-option>
                            <div class="karcis-body">
                                <div class="karcis-kicker">TIKET</div>
                                <div class="fw-semibold">{{ $category->name }}</div>
                                <div class="karcis-price">{!! $category->price > 0 ? e($rupiah($category->price)) : '<span class="badge bg-success">Gratis</span>' !!}</div>
                                @if ($category->description) <div class="small text-muted">{{ $category->description }}</div> @endif
                                @if ($reason)
                                    <div class="small text-danger">{{ $reason }}</div>
                                @elseif ($category->remaining() <= 10)
                                    <div class="small text-warning">Sisa {{ $category->remaining() }} tiket</div>
                                @endif
                            </div>
                            @unless ($reason)
                                <div class="karcis-stub">
                                <div class="input-group input-group-sm qty flex-shrink-0">
                                    <button type="button" class="btn btn-outline-secondary" data-step="-1" aria-label="Kurangi">−</button>
                                    <input type="number" name="tickets[{{ $category->id }}]" class="form-control" inputmode="numeric" min="0" max="{{ $category->maxBuyable() }}"
                                        value="{{ min((int) old('tickets.'.$category->id), $category->maxBuyable()) }}"
                                        data-price="{{ $category->price }}" data-name="{{ $category->name }}" aria-label="Jumlah tiket {{ $category->name }}">
                                    <button type="button" class="btn btn-outline-secondary" data-step="1" aria-label="Tambah">+</button>
                                </div>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card shadow-sm" style="grid-area: buyer;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">1</span> Data Pemesan</h2>
                    <div class="row g-3">
                        @foreach (['name' => ['Nama lengkap', 'text', 'col-12'], 'email' => ['Email', 'email', 'col-md-6'], 'phone' => ['No. WhatsApp', 'tel', 'col-md-6']] as $field => [$label, $type, $col])
                            <div class="{{ $col }}">
                                <label for="{{ $field }}" class="form-label mb-1">{{ $label }} <span class="text-danger">*</span></label>
                                <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" required>
                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                        <div class="col-12 form-text mt-1">E-ticket dikirim ke email ini.</div>
                    </div>

                    @if ($page->fields->isNotEmpty())
                        <hr class="my-4">
                        <h2 class="h6 fw-semibold mb-3">Data Tambahan</h2>
                        @include('page.public._fields', ['fields' => $page->fields, 'preview' => false])
                    @endif

                </div>
            </div>

            <div class="card shadow-sm ticket-summary" style="grid-area: summary;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">3</span> Ringkasan & Persetujuan</h2>
                    <div id="ticket-lines" class="small"></div>
                    <div id="ticket-empty" class="small text-muted">Belum ada tiket dipilih.</div>
                    <div id="ticket-fee" class="small d-flex justify-content-between gap-2 d-none"><span>Biaya layanan</span><span class="text-nowrap" id="ticket-fee-amount"></span></div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center fw-semibold mb-3">
                        <span>Total</span><span class="fs-5" id="ticket-total">Rp 0</span>
                    </div>
                    @if ($terms = $page->setting('ticket.terms'))
                        {{-- S&K dibuka sebagai popup supaya halaman tidak memanjang; menutup popup = sudah dibaca, otomatis tercentang. --}}
                        <div class="form-check small mb-3">
                            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" @checked(old('terms')) required>
                            <label class="form-check-label" for="terms">Saya sudah membaca dan menyetujui <a href="#" data-open-terms class="fw-semibold text-brand">Syarat & Ketentuan</a>.</label>
                        </div>
                        <dialog class="terms-dialog" id="terms-dialog">
                            <div class="p-4">
                                <h3 class="h6 fw-semibold mb-3">Syarat & Ketentuan</h3>
                                <div class="small text-muted" style="white-space: pre-line; max-height: 60vh; overflow-y: auto;">{{ $terms }}</div>
                                <button type="button" class="btn btn-order w-100 mt-4" data-close-terms>Saya Mengerti & Setuju</button>
                            </div>
                        </dialog>
                    @endif
                    <div class="mb-3">@include('auth.partials.turnstile')</div>
                    <button type="submit" class="btn btn-order w-100 py-2" id="ticket-submit" disabled>Pesan Sekarang</button>
                    <div class="form-text text-center mt-2" id="ticket-note">Tiket ditahan selama batas waktu pembayaran. Kalau tidak dibayar, tiket dilepas otomatis.</div>
                </div>
            </div>
        </div>
    </form>

    <script>
        (function () {
            var form = document.getElementById('ticket-form');
            var inputs = form.querySelectorAll('input[data-price]');
            var money = function (n) { return 'Rp ' + n.toLocaleString('id-ID'); };
            var escape = function (text) { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; };

            function clamp(input) {
                var value = Math.max(0, Math.min(Number(input.max), parseInt(input.value, 10) || 0));
                input.value = value;
                return value;
            }

            function update() {
                var total = 0, count = 0, lines = '';
                inputs.forEach(function (input) {
                    var qty = clamp(input), price = Number(input.dataset.price);
                    input.closest('[data-option]').classList.toggle('is-picked', qty > 0);
                    if (!qty) return;
                    total += qty * price; count += qty;
                    lines += '<div class="d-flex justify-content-between gap-2 mb-1"><span>' + escape(input.dataset.name) + ' × ' + qty + '</span><span class="text-nowrap">' + (price ? money(qty * price) : 'Gratis') + '</span></div>';
                });
                var bps = Number(form.dataset.feeBps) || 0;
                var fee = total > 0 && bps > 0 ? Math.floor((total * bps + 9999) / 10000) : 0;
                document.getElementById('ticket-fee').classList.toggle('d-none', fee === 0);
                document.getElementById('ticket-fee-amount').textContent = money(fee);
                var free = count > 0 && total === 0;
                total += fee;
                document.getElementById('ticket-lines').innerHTML = lines;
                document.getElementById('ticket-empty').classList.toggle('d-none', count > 0);
                document.getElementById('ticket-total').textContent = free ? 'Gratis' : money(total);
                document.getElementById('ticket-note').classList.toggle('d-none', free);
                var terms = document.getElementById('terms');
                document.getElementById('ticket-submit').disabled = count === 0 || (terms && !terms.checked);
            }

            form.addEventListener('click', function (event) {
                var button = event.target.closest('[data-step]');
                if (!button) return;
                var input = button.parentElement.querySelector('input');
                input.value = (parseInt(input.value, 10) || 0) + Number(button.dataset.step);
                update();
            });
            var dialog = document.getElementById('terms-dialog');
            if (dialog) {
                form.querySelector('[data-open-terms]').addEventListener('click', function (event) { event.preventDefault(); dialog.showModal(); });
                dialog.querySelector('[data-close-terms]').addEventListener('click', function () {
                    dialog.close();
                    document.getElementById('terms').checked = true;
                    update();
                });
            }
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        })();
    </script>
@endif
