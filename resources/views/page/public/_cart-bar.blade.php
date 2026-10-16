{{-- Keranjang disimpan di browser pembeli (per page). Harga di sini hanya tampilan; total resmi dihitung server saat checkout. --}}
<div class="cart-bar bg-white border-top shadow-lg py-2 d-none" id="cart-bar">
    <div class="container d-flex align-items-center justify-content-between gap-2" style="max-width: 1140px;">
        <div>
            <div class="small text-muted"><span id="cart-count">0</span> item</div>
            <div class="fw-semibold">Rp <span id="cart-total">0</span></div>
        </div>
        <a href="{{ route('page.checkout', $page->slug) }}" class="btn btn-primary"><i class="ri-shopping-cart-2-line me-1"></i>Checkout</a>
    </div>
</div>

@push('scripts')
    <script>
        window.PageCart = (function () {
            var key = 'cart:{{ $page->slug }}';
            var format = function (n) { return Number(n).toLocaleString('id-ID'); };

            function read() {
                try { return JSON.parse(localStorage.getItem(key)) || {}; } catch (e) { return {}; }
            }
            function write(cart) {
                try { localStorage.setItem(key, JSON.stringify(cart)); } catch (e) {}
                render();
            }
            function render() {
                var cart = read(), count = 0, total = 0;
                Object.keys(cart).forEach(function (id) { count += cart[id].qty; total += cart[id].qty * cart[id].price; });
                var bar = document.getElementById('cart-bar');
                if (bar) {
                    bar.classList.toggle('d-none', count === 0);
                    document.getElementById('cart-count').textContent = count;
                    document.getElementById('cart-total').textContent = format(total);
                }
                document.dispatchEvent(new CustomEvent('cart:changed', { detail: cart }));
            }

            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-add-to-cart]');
                if (!button) return;
                var cart = read(), id = button.dataset.id, stock = button.dataset.stock === '' ? Infinity : Number(button.dataset.stock);
                var item = cart[id] || { name: button.dataset.name, price: Number(button.dataset.price), thumb: button.dataset.thumb, qty: 0, stock: stock };
                if (item.qty >= stock) { alert('Stok ' + item.name + ' tidak cukup.'); return; }
                item.qty += 1;
                cart[id] = item;
                write(cart);
                button.innerHTML = '<i class="ri-check-line me-1"></i>Ditambahkan';
                setTimeout(function () { button.innerHTML = '<i class="ri-shopping-cart-2-line me-1"></i>Tambah'; }, 1200);
            });

            @if (session('ordered'))
                try { localStorage.removeItem(key); } catch (e) {}
            @endif

            document.addEventListener('DOMContentLoaded', render);

            return { read: read, write: write, format: format };
        })();
    </script>
@endpush
