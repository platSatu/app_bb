@php($rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
@push('head')
    <style>
        .ticket-grid { display: grid; gap: 1.25rem; grid-template-columns: 1fr; grid-template-areas: "tickets" "buyer" "summary"; }
        @media (min-width: 992px) {
            .ticket-grid { grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); grid-template-areas: "buyer tickets" "buyer summary"; align-items: start; }
            .ticket-summary { position: sticky; top: 1.5rem; }
        }
        .ticket-grid .card { border: 0; border-radius: 1rem; }
        .ticket-option { border: 1px solid #e9ecef; border-radius: .75rem; padding: .875rem 1rem; transition: border-color .15s, background .15s; }
        .ticket-option.is-picked { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 5%, #fff); }
        .qty { width: 116px; }
        .qty .form-control { text-align: center; -moz-appearance: textfield; }
        .qty .form-control::-webkit-inner-spin-button, .qty .form-control::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .step-number { width: 26px; height: 26px; border-radius: 50%; background: var(--brand); color: #fff; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; }
    </style>
@endpush

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4 text-center">
        <h1 class="h3 fw-bold mb-1">{{ $page->name }}</h1>
        @if ($page->start_date)
            <div class="text-muted"><i class="ri-calendar-event-line"></i> {{ $page->start_date->translatedFormat('l, d F Y') }}</div>
        @endif
        @if ($page->description)
            <p class="text-muted mb-0 mt-2" style="white-space: pre-line;">{{ $page->description }}</p>
        @endif
    </div>
</div>

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
@endif

@if ($ticketCategories->isEmpty())
    <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 text-center text-muted">Tiket belum dibuka. Pantau terus halaman ini ya.</div></div>
@else
    <form method="POST" action="{{ route('page.tickets.checkout', $page->slug) }}" id="ticket-form" enctype="multipart/form-data" onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf
        @include('page.public._honeypot')

        <div class="ticket-grid">
            <div class="card shadow-sm" style="grid-area: tickets;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">1</span> Pilih Tiket</h2>
                    @foreach ($ticketCategories as $category)
                        @php($reason = $category->unavailableReason())
                        <div class="ticket-option d-flex align-items-center justify-content-between gap-3 mb-2 {{ $reason ? 'bg-light opacity-75' : '' }}" data-option>
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $category->name }}</div>
                                <div class="small">{!! $category->price > 0 ? e($rupiah($category->price)) : '<span class="badge bg-success">Gratis</span>' !!}</div>
                                @if ($category->description) <div class="small text-muted">{{ $category->description }}</div> @endif
                                @if ($reason)
                                    <div class="small text-danger">{{ $reason }}</div>
                                @elseif ($category->remaining() <= 10)
                                    <div class="small text-warning">Sisa {{ $category->remaining() }} tiket</div>
                                @endif
                            </div>
                            @unless ($reason)
                                <div class="input-group input-group-sm qty flex-shrink-0">
                                    <button type="button" class="btn btn-outline-secondary" data-step="-1" aria-label="Kurangi">−</button>
                                    <input type="number" name="tickets[{{ $category->id }}]" class="form-control" inputmode="numeric" min="0" max="{{ $category->maxBuyable() }}"
                                        value="{{ min((int) old('tickets.'.$category->id), $category->maxBuyable()) }}"
                                        data-price="{{ $category->price }}" data-name="{{ $category->name }}" aria-label="Jumlah tiket {{ $category->name }}">
                                    <button type="button" class="btn btn-outline-secondary" data-step="1" aria-label="Tambah">+</button>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card shadow-sm" style="grid-area: buyer;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">2</span> Data Pemesan</h2>
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
                        <h2 class="h6 fw-semibold mb-3 d-flex align-items-center gap-2"><span class="step-number">3</span> Data Tambahan</h2>
                        @include('page.public._fields', ['fields' => $page->fields, 'preview' => false])
                    @endif

                    <div class="mt-3">@include('auth.partials.turnstile')</div>
                </div>
            </div>

            <div class="card shadow-sm ticket-summary" style="grid-area: summary;">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3">Ringkasan Pesanan</h2>
                    <div id="ticket-lines" class="small"></div>
                    <div id="ticket-empty" class="small text-muted">Belum ada tiket dipilih.</div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center fw-semibold mb-3">
                        <span>Total</span><span class="fs-5" id="ticket-total">Rp 0</span>
                    </div>
                    <button type="submit" class="btn btn-brand w-100 py-2" id="ticket-submit" disabled>Lanjut ke Pembayaran</button>
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
                var free = count > 0 && total === 0;
                document.getElementById('ticket-lines').innerHTML = lines;
                document.getElementById('ticket-empty').classList.toggle('d-none', count > 0);
                document.getElementById('ticket-total').textContent = free ? 'Gratis' : money(total);
                document.getElementById('ticket-submit').textContent = free ? 'Daftar Sekarang' : 'Lanjut ke Pembayaran';
                document.getElementById('ticket-note').classList.toggle('d-none', free);
                document.getElementById('ticket-submit').disabled = count === 0;
            }

            form.addEventListener('click', function (event) {
                var button = event.target.closest('[data-step]');
                if (!button) return;
                var input = button.parentElement.querySelector('input');
                input.value = (parseInt(input.value, 10) || 0) + Number(button.dataset.step);
                update();
            });
            form.addEventListener('input', update);
            update();
        })();
    </script>
@endif
