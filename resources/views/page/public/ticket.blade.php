@php($rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div class="card shadow-sm">
    <div class="card-body p-4 p-md-5">
        <h1 class="h3 mb-1 text-center">{{ $page->name }}</h1>
        @if ($page->start_date)
            <p class="text-center text-muted mb-1"><i class="ri-calendar-event-line"></i> {{ $page->start_date->translatedFormat('l, d F Y') }}</p>
        @endif
        @if ($page->description)
            <p class="text-muted text-center mb-4" style="white-space: pre-line;">{{ $page->description }}</p>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
        @endif

        @if ($ticketCategories->isEmpty())
            <p class="text-center text-muted mb-0">Tiket belum dibuka. Pantau terus halaman ini ya.</p>
        @else
            <form method="POST" action="{{ route('page.tickets.checkout', $page->slug) }}" id="ticket-form" onsubmit="this.querySelector('[type=submit]').disabled = true">
                @csrf
                @include('page.public._honeypot')

                <h2 class="h6 fw-semibold mb-2">1. Pilih Tiket</h2>
                <div class="mb-4">
                    @foreach ($ticketCategories as $category)
                        @php($reason = $category->unavailableReason())
                        <div class="d-flex align-items-center justify-content-between gap-3 border rounded-3 p-3 mb-2 {{ $reason ? 'bg-light opacity-75' : '' }}">
                            <div>
                                <div class="fw-semibold">{{ $category->name }}</div>
                                <div>{{ $rupiah($category->price) }}</div>
                                @if ($category->description) <div class="small text-muted">{{ $category->description }}</div> @endif
                                @if ($reason)
                                    <div class="small text-danger">{{ $reason }}</div>
                                @elseif ($category->remaining() <= 10)
                                    <div class="small text-warning">Sisa {{ $category->remaining() }} tiket</div>
                                @endif
                            </div>
                            @unless ($reason)
                                <select name="tickets[{{ $category->id }}]" class="form-select" style="width: 90px;" data-price="{{ $category->price }}">
                                    @for ($n = 0; $n <= $category->maxBuyable(); $n++)
                                        <option value="{{ $n }}" @selected((int) old('tickets.'.$category->id) === $n)>{{ $n }}</option>
                                    @endfor
                                </select>
                            @endunless
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-between fw-semibold px-1">
                        <span>Total</span><span>Rp <span id="ticket-total">0</span></span>
                    </div>
                </div>

                <h2 class="h6 fw-semibold mb-2">2. Data Pemesan</h2>
                @foreach (['name' => ['Nama', 'text'], 'email' => ['Email (e-ticket dikirim ke sini)', 'email'], 'phone' => ['No. WhatsApp', 'tel']] as $field => [$label, $type])
                    <div class="mb-3">
                        <label for="{{ $field }}" class="form-label">{{ $label }} <span class="text-danger">*</span></label>
                        <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" required>
                        @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach

                @include('auth.partials.turnstile')
                <button type="submit" class="btn btn-primary w-100" id="ticket-submit" disabled>Lanjut ke Pembayaran</button>
                <div class="form-text text-center">Tiket ditahan untuk Anda selama batas waktu pembayaran. Kalau tidak dibayar, tiket dilepas otomatis.</div>
            </form>

            <script>
                (function () {
                    var form = document.getElementById('ticket-form');
                    function update() {
                        var total = 0, count = 0;
                        form.querySelectorAll('select[data-price]').forEach(function (s) { total += s.value * s.dataset.price; count += Number(s.value); });
                        document.getElementById('ticket-total').textContent = total.toLocaleString('id-ID');
                        document.getElementById('ticket-submit').disabled = count === 0;
                    }
                    form.addEventListener('change', update);
                    update();
                })();
            </script>
        @endif
    </div>
</div>
