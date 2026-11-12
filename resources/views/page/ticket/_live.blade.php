{{--
    Pantau Live (tab Laporan): stok, uang, alur stok & callback terbaru, email, cek konsistensi.
    Data awal dari server; setelah itu diperbarui tiap 5 detik lewat ?tab=live&poll=1 (berhenti saat tab browser tidak dilihat).
--}}
@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))
<div id="live" data-url="{{ request()->fullUrlWithQuery(['tab' => 'live', 'poll' => 1]) }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="small text-muted d-inline-flex align-items-center gap-2"><span class="spinner-grow spinner-grow-sm text-success" style="width:.6rem;height:.6rem" aria-hidden="true"></span>Diperbarui otomatis tiap 5 detik · terakhir <span data-live="at">{{ $live['at'] }}</span></div>
        <div class="small" data-live="health"></div>
    </div>

    <div class="row g-3 mb-3" data-live="money"></div>

    <div class="table-responsive border rounded-3 mb-3">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th>Kategori</th><th class="text-end">Kuota</th><th class="text-end">Terjual</th><th class="text-end">Ditahan</th><th class="text-end">Sisa</th><th>Terisi</th><th>Konsistensi</th></tr></thead>
            <tbody data-live="categories"></tbody>
        </table>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
                <div class="fw-semibold mb-2"><i class="ri-stack-line text-primary me-1"></i>Pergerakan stok terbaru</div>
                <div class="small" data-live="stock"></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
                <div class="fw-semibold mb-2"><i class="ri-bank-card-line text-primary me-1"></i>Callback Duitku terbaru</div>
                <div class="small" data-live="callbacks"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var box = document.getElementById('live');
            if (!box) return;
            var part = function (name) { return box.querySelector('[data-live="' + name + '"]'); };
            var rp = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };
            function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; }
            function rows(target, items, empty, build) {
                target.innerHTML = '';
                if (!items.length) { target.appendChild(el('div', 'text-muted', empty)); return; }
                items.forEach(function (item) { target.appendChild(build(item)); });
            }

            function render(d) {
                part('at').textContent = d.at;
                var o = d.orders, money = part('money'); money.innerHTML = '';
                [
                    ['Lunas', o.paid.orders + ' pesanan', rp(o.paid.total), 'Masuk saldo ' + rp(o.paid.net) + (o.paid.recent ? ' · +' + o.paid.recent + ' dalam 5 menit' : ''), 'text-success'],
                    ['Menunggu bayar', o.pending.orders + ' pesanan', rp(o.pending.total), 'Stok ditahan sampai lunas atau kedaluwarsa', 'text-warning'],
                    ['Gagal / kedaluwarsa', (o.failed.orders + o.expired.orders) + ' pesanan', '', 'Stoknya sudah dikembalikan', 'text-muted'],
                    ['Perlu refund', o.refund_needed.orders + ' pesanan', o.refund_needed.orders ? rp(o.refund_needed.total) : '', 'Dibayar setelah batas waktu & kuota habis', o.refund_needed.orders ? 'text-danger' : 'text-muted']
                ].forEach(function (c) {
                    var col = el('div', 'col-6 col-xl-3'), card = el('div', 'border rounded-3 p-3 h-100');
                    card.appendChild(el('div', 'small text-muted', c[0]));
                    card.appendChild(el('div', 'fs-5 fw-bold ' + c[4], c[1]));
                    if (c[2]) card.appendChild(el('div', 'fw-semibold', c[2]));
                    card.appendChild(el('div', 'small text-muted', c[3]));
                    col.appendChild(card); money.appendChild(col);
                });

                var bad = 0;
                rows(part('categories'), d.categories, 'Belum ada kategori tiket.', function (c) {
                    var tr = el('tr'), pct = c.quota ? Math.min(100, Math.round((c.sold + c.reserved) / c.quota * 100)) : 0;
                    [c.name, c.quota, c.sold, c.reserved, c.remaining].forEach(function (v, i) { tr.appendChild(el('td', i ? 'text-end' : 'fw-semibold', i ? Number(v).toLocaleString('id-ID') : v)); });
                    var bar = el('td'), track = el('div', 'progress'), fill = el('div', 'progress-bar bg-success');
                    track.style.height = '6px'; track.style.minWidth = '80px'; fill.style.width = pct + '%'; track.appendChild(fill); bar.appendChild(track); tr.appendChild(bar);
                    if (!c.ok) bad++;
                    tr.appendChild(el('td', c.ok ? 'text-success' : 'text-danger fw-semibold', c.ok ? 'Cocok' : 'Selisih: e-ticket terbit ' + c.issued + ', menunggu bayar ' + c.waiting));
                    return tr;
                });

                var emails = d.emails || {}, health = part('health'); health.innerHTML = '';
                health.appendChild(el('span', 'badge me-1 ' + (bad ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'), bad ? bad + ' kategori stok tidak cocok' : 'Stok konsisten'));
                health.appendChild(el('span', 'badge me-1 bg-light text-body', 'Email antre ' + (emails.queued || 0)));
                health.appendChild(el('span', 'badge ' + (emails.failed ? 'bg-danger-subtle text-danger' : 'bg-light text-body'), 'Email gagal ' + (emails.failed || 0)));

                rows(part('stock'), d.stock, 'Belum ada pergerakan stok.', function (s) {
                    var row = el('div', 'd-flex gap-2 py-1 border-bottom');
                    row.appendChild(el('span', 'text-muted text-nowrap', s.at));
                    var body = el('div', 'min-w-0'); body.appendChild(el('div', 'fw-semibold', s.action + ' · ' + (s.category || '-')));
                    body.appendChild(el('div', 'text-muted text-truncate', s.changes + (s.order ? ' · ' + s.order : ''))); row.appendChild(body);
                    return row;
                });
                rows(part('callbacks'), d.callbacks, 'Belum ada callback.', function (c) {
                    var row = el('div', 'd-flex gap-2 py-1 border-bottom align-items-start');
                    row.appendChild(el('span', 'text-muted text-nowrap', c.at));
                    var body = el('div', 'min-w-0 flex-grow-1'); body.appendChild(el('div', 'fw-semibold', c.order + ' · ' + rp(c.amount))); row.appendChild(body);
                    row.appendChild(el('span', 'badge ' + c.badge, c.result));
                    return row;
                });
            }

            render(@json($live));
            var busy = false;
            setInterval(function () {
                if (busy || document.hidden) return;
                busy = true;
                fetch(box.dataset.url, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { if (!r.ok) throw r.status; return r.json(); })
                    .then(render).catch(function () {}).finally(function () { busy = false; });
            }, 5000);
        })();
    </script>
@endpush
