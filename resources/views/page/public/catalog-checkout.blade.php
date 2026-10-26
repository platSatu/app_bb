@extends('page.public.layout')

@section('title', 'Checkout · '.$page->name)
@section('width', '960px')

@section('content')
    <a href="{{ $page->url() }}" class="btn btn-sm btn-light mb-3"><i class="ri-arrow-left-line"></i> Lanjut belanja</a>

    <form method="POST" action="{{ route('page.checkout.store', $page->slug) }}" id="checkout-form" onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf
        @include('page.public._honeypot')

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->get('cart') ?: ($errors->get('payment') ?: ['Ada isian yang belum sesuai, silakan periksa kembali.']) as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-white px-4 py-3"><h6 class="mb-0 fw-semibold">Keranjang</h6></div>
                    <div class="card-body p-3 p-md-4">
                        <div id="cart-items"></div>
                        <div id="cart-empty" class="text-center text-muted py-4 d-none">
                            Keranjang masih kosong. <a href="{{ $page->url() }}">Pilih produk dulu</a>.
                        </div>
                        <div class="d-flex justify-content-between border-top pt-3 mt-2">
                            <span class="fw-semibold">Total</span>
                            <span class="fw-semibold fs-5">Rp <span id="checkout-total">0</span></span>
                        </div>
                        <div class="form-text">Total akhir dihitung ulang sesuai harga & stok terbaru saat pesanan dikirim.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white px-4 py-3"><h6 class="mb-0 fw-semibold">Data Pemesan</h6></div>
                    <div class="card-body p-3 p-md-4">
                        @foreach ([
                            'name' => ['Nama', 'text', true],
                            'phone' => ['No. WhatsApp', 'tel', true],
                            'email' => [in_array(\App\Models\CatalogOrder::PAYMENT_ONLINE, $paymentMethods, true) ? 'Email (wajib untuk bayar online)' : 'Email (opsional)', 'email', false],
                        ] as $field => [$label, $type, $required])
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" @required($required)>
                                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                        <div class="mb-3">
                            <label for="address" class="form-label">Alamat Pengiriman <span class="text-danger">*</span></label>
                            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" required>{{ old('address') }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Catatan (opsional)</label>
                            <textarea id="note" name="note" rows="2" class="form-control">{{ old('note') }}</textarea>
                        </div>
                        @if ($paymentMethods)
                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                                @foreach ($paymentMethods as $method)
                                    <div class="form-check border rounded-3 p-2 ps-5 mb-2">
                                        <input class="form-check-input" type="radio" name="payment" id="payment-{{ $method }}" value="{{ $method }}" required
                                            @checked(old('payment', count($paymentMethods) === 1 ? $method : null) === $method)>
                                        <label class="form-check-label w-100" for="payment-{{ $method }}">{{ \App\Models\CatalogOrder::PAYMENT_LABELS[$method] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @include('auth.partials.turnstile')
                        <button type="submit" class="btn btn-primary w-100" id="checkout-submit">{{ $paymentMethods ? 'Pesan & Bayar' : 'Kirim Pesanan' }}</button>
                        <div class="form-text text-center">{{ $paymentMethods ? 'Rincian pembayaran tampil setelah pesanan dibuat.' : 'Pembayaran & pengiriman dikonfirmasi langsung oleh penjual.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @include('page.public._cart-bar')

    @push('scripts')
        <script>
            (function () {
                var list = document.getElementById('cart-items');
                var form = document.getElementById('checkout-form');

                function escapeHtml(text) { var d = document.createElement('div'); d.textContent = text; return d.innerHTML; }

                function render(cart) {
                    var ids = Object.keys(cart), total = 0;
                    document.getElementById('cart-empty').classList.toggle('d-none', ids.length > 0);
                    document.getElementById('checkout-submit').disabled = ids.length === 0;
                    document.getElementById('cart-bar').classList.add('d-none');
                    form.querySelectorAll('[data-cart-input]').forEach(function (input) { input.remove(); });

                    list.innerHTML = ids.map(function (id) {
                        var item = cart[id];
                        total += item.qty * item.price;
                        form.insertAdjacentHTML('beforeend', '<input type="hidden" data-cart-input name="items[' + id + ']" value="' + item.qty + '">');
                        return '<div class="d-flex align-items-center gap-3 mb-3">'
                            + (item.thumb ? '<img src="' + item.thumb + '" width="56" height="56" class="rounded border product-thumb" alt="">' : '')
                            + '<div class="flex-grow-1"><div class="fw-semibold small">' + escapeHtml(item.name) + '</div>'
                            + '<div class="small text-muted">Rp ' + PageCart.format(item.price) + '</div></div>'
                            + '<div class="input-group input-group-sm" style="width: 110px;">'
                            + '<button type="button" class="btn btn-outline-secondary" data-qty="-1" data-id="' + id + '">−</button>'
                            + '<span class="form-control text-center">' + item.qty + '</span>'
                            + '<button type="button" class="btn btn-outline-secondary" data-qty="1" data-id="' + id + '">+</button></div></div>';
                    }).join('');

                    document.getElementById('checkout-total').textContent = PageCart.format(total);
                }

                list.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-qty]');
                    if (!button) return;
                    var cart = PageCart.read(), item = cart[button.dataset.id];
                    if (!item) return;
                    var next = item.qty + Number(button.dataset.qty);
                    if (next > (item.stock === null ? Infinity : item.stock)) { alert('Stok ' + item.name + ' tidak cukup.'); return; }
                    if (next <= 0) delete cart[button.dataset.id]; else item.qty = next;
                    PageCart.write(cart);
                });

                document.addEventListener('cart:changed', function (event) { render(event.detail); });
            })();
        </script>
    @endpush
@endsection
