@extends('page.public.layout', ['open' => false])

@section('title', 'Scan · '.$page->name)
@section('width', '1200px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Warna halaman mengikuti warna tiket, supaya serasi dengan e-ticket & ID card. --}}
    @if ($page->setting('ticket.color'))
        <style>:root:root { --brand: {{ $page->setting('ticket.color') }}; }</style>
    @endif
    <style>
        .scan-card { border: 0; border-radius: 1.25rem; box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 10px 30px rgba(16,24,40,.06); }
        .scan-input-wrap { position: relative; }
        .scan-input-wrap i { position: absolute; left: 1.25rem; top: 50%; transform: translateY(-50%); font-size: 1.75rem; color: var(--brand); }
        .scan-input-wrap input { padding-left: 3.75rem; border-radius: 1rem; height: 72px; font-size: 1.25rem; border: 2px solid #ebeaf0; background: #fcfcfd; }
        .scan-input-wrap input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 .3rem color-mix(in srgb, var(--brand) 14%, transparent); }
        .result-icon { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem; background: #fff; box-shadow: 0 4px 14px rgba(16,24,40,.08); }
        .gate-badge { background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); border-radius: 999px; padding: .2rem .75rem; font-size: .8125rem; font-weight: 600; }
        .live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,.6); animation: live 1.8s infinite; }
        @keyframes live { 70% { box-shadow: 0 0 0 8px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        /* 3 kotak sejajar dengan tinggi sama; isi yang terus bertambah digulir di dalam kotaknya. */
        .scan-box { display: flex; flex-direction: column; height: 100%; max-height: 440px; }
        @media (min-width: 992px) { .scan-box { height: 400px; max-height: none; } }
        .scan-box-head { display: flex; align-items: center; gap: .6rem; padding: 1.1rem 1.25rem .75rem; font-weight: 600; }
        .scan-box-head .icon { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
        .scan-box-body { flex: 1; min-height: 0; overflow-y: auto; padding: 0 1.25rem 1.25rem; scrollbar-width: thin; }
        .scan-result { border-radius: 1rem; border-width: 2px !important; padding: 1.5rem 1rem; text-align: center; }
        .stat-big { font-size: 3rem; font-weight: 700; line-height: 1; letter-spacing: -.02em; }
        .history-item { border-bottom: 1px dashed #ececf1; padding: .65rem 0; }
        .history-item:last-child { border-bottom: 0; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: .4rem; }
    </style>
@endpush

@section('content')
    <div class="card scan-card shadow-sm mb-4">
        <div class="card-body p-3 px-md-4 d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="result-icon flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.5rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand);"><i class="ri-qr-scan-2-line"></i></span>
                <div class="min-w-0">
                    <div class="fw-bold text-truncate">{{ $page->name }}</div>
                    <span class="gate-badge"><i class="ri-user-location-line me-1"></i>Petugas {{ $scanner->name }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('scan.logout', request()->route('token')) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-light"><i class="ri-logout-box-r-line"></i> Keluar</button>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card scan-card scan-box">
                <div class="scan-box-head"><span class="icon"><i class="ri-notification-3-line"></i></span>Notifikasi</div>
                <div class="scan-box-body">
                    <div id="result" class="scan-result border d-none">
                        <span class="result-icon mb-2"><i id="result-icon"></i></span>
                        <div id="result-message" class="fs-5 fw-bold mt-2"></div>
                        <div id="result-detail" class="mt-2 small"></div>
                        <button type="button" id="confirm-button" class="btn btn-success px-4 mt-3 d-none"><i class="ri-check-line me-1"></i>Konfirmasi Hadir</button>
                        @if ($cardEnabled)
                            <div id="card-actions" class="d-none mt-3 pt-3 border-top">
                                <div class="small text-muted mb-2" id="card-hint"></div>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-dark" data-card="0"><i class="ri-printer-line me-1"></i><span>Cetak ID card</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger d-none" data-card="1"><i class="ri-refresh-line me-1"></i>Kartu hilang, buat QR baru</button>
                                </div>
                                <div class="small mt-2" id="card-message"></div>
                            </div>
                        @endif
                    </div>
                    <div id="result-empty" class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                        <i class="ri-scan-line fs-1 mb-2"></i>Hasil scan akan tampil di sini.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card scan-card scan-box">
                <div class="scan-box-head"><span class="icon"><i class="ri-team-line"></i></span>Sudah hadir</div>
                <div class="scan-box-body">
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="stat-big" id="stat-in">{{ $stats['checked_in'] }}</span>
                        <span class="text-muted mb-1">/ <span id="stat-total">{{ $stats['total'] }}</span> tiket</span>
                    </div>
                    <div class="progress mb-4" style="height: 10px; border-radius: 99px;"><div class="progress-bar" id="stat-bar" style="background: var(--brand); width: 0%;"></div></div>
                    <div id="stat-categories" class="small"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card scan-card scan-box">
                <div class="scan-box-head"><span class="icon"><i class="ri-history-line"></i></span>Scan terakhir</div>
                <div class="scan-box-body">
                    <div id="history" class="small"></div>
                    <div id="history-empty" class="h-100 d-flex align-items-center justify-content-center small text-muted">Belum ada scan di perangkat ini.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card scan-card">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                <h2 class="h5 fw-bold mb-0">Registrasi Ulang</h2>
                <span class="small text-muted d-inline-flex align-items-center gap-2"><span class="live-dot"></span>Siap menerima scan</span>
            </div>
            <p class="small text-muted mb-3">Arahkan scanner ke QR e-ticket atau QR ID card, atau ketik kode booking 6 angka lalu tekan Enter.</p>
            <form id="scan-form" autocomplete="off">
                <div class="scan-input-wrap">
                    <i class="ri-qr-code-line"></i>
                    <input type="text" id="scan-input" class="form-control" autofocus placeholder="Scan QR atau ketik kode booking" maxlength="64">
                </div>
            </form>
        </div>
    </div>

    <iframe id="print-frame" title="Cetak ID card" style="position: absolute; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

    @push('scripts')
        <script>
            (function () {
                var input = document.getElementById('scan-input');
                var result = document.getElementById('result');
                var confirmButton = document.getElementById('confirm-button');
                var styles = {
                    ok: ['border-success bg-success-subtle', 'ri-checkbox-circle-fill text-success', '#198754'],
                    already: ['border-warning bg-warning-subtle', 'ri-error-warning-fill text-warning', '#ffc107'],
                    confirm: ['border-primary bg-primary-subtle', 'ri-user-search-line text-primary', '#0d6efd'],
                    not_found: ['border-danger bg-danger-subtle', 'ri-close-circle-fill text-danger', '#dc3545'],
                    error: ['border-danger bg-danger-subtle', 'ri-wifi-off-line text-danger', '#dc3545']
                };
                var pendingCode = null, busy = false, history = [];

                function el(tag, className, text) {
                    var node = document.createElement(tag);
                    if (className) node.className = className;
                    if (text !== undefined) node.textContent = text;
                    return node;
                }

                function beep(ok) {
                    try {
                        var ctx = new (window.AudioContext || window.webkitAudioContext)(), osc = ctx.createOscillator();
                        osc.frequency.value = ok ? 880 : 220; osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.15);
                    } catch (e) {}
                }

                function renderStats(stats) {
                    document.getElementById('stat-in').textContent = stats.checked_in;
                    document.getElementById('stat-total').textContent = stats.total;
                    document.getElementById('stat-bar').style.width = (stats.total ? stats.checked_in / stats.total * 100 : 0) + '%';
                    var box = document.getElementById('stat-categories');
                    box.innerHTML = '';
                    stats.categories.forEach(function (c) {
                        var row = el('div', 'mb-3');
                        var head = el('div', 'd-flex justify-content-between mb-1 gap-2');
                        head.appendChild(el('span', 'fw-semibold', c.name));
                        head.appendChild(el('span', 'text-muted', c.checked_in + ' / ' + c.total));
                        var bar = el('div', 'progress'); bar.style.height = '6px';
                        var fill = el('div', 'progress-bar'); fill.style.background = 'var(--brand)';
                        fill.style.width = (c.total ? c.checked_in / c.total * 100 : 0) + '%';
                        bar.appendChild(fill); row.appendChild(head); row.appendChild(bar); box.appendChild(row);
                    });
                }

                function addHistory(data) {
                    if (data.status === 'confirm') return;
                    history.unshift({ color: (styles[data.status] || styles.error)[2], title: data.ticket ? data.ticket.name : data.message, detail: data.ticket ? data.ticket.category + ' · ' + data.ticket.booking_code + ' · ' + data.message : '', time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) });
                    history = history.slice(0, 50);
                    var box = document.getElementById('history');
                    box.innerHTML = '';
                    history.forEach(function (h) {
                        var row = el('div', 'history-item d-flex gap-2');
                        var dot = el('span', 'dot'); dot.style.background = h.color;
                        var body = el('div', 'flex-grow-1 min-w-0');
                        var top = el('div', 'd-flex justify-content-between gap-2');
                        top.appendChild(el('span', 'fw-semibold text-truncate', h.title));
                        top.appendChild(el('span', 'text-muted text-nowrap', h.time));
                        body.appendChild(top);
                        if (h.detail) body.appendChild(el('div', 'text-muted text-truncate', h.detail));
                        row.appendChild(dot); row.appendChild(body); box.appendChild(row);
                    });
                    document.getElementById('history-empty').classList.toggle('d-none', history.length > 0);
                    document.getElementById('history-empty').classList.toggle('d-flex', history.length === 0);
                }

                function show(data) {
                    var style = styles[data.status] || styles.error;
                    document.getElementById('result-empty').classList.add('d-none');
                    document.getElementById('result-empty').classList.remove('d-flex');
                    result.className = 'scan-result border ' + style[0];
                    document.getElementById('result-icon').className = style[1];
                    document.getElementById('result-message').textContent = data.message;
                    var detail = document.getElementById('result-detail');
                    detail.innerHTML = '';
                    if (data.ticket) {
                        detail.appendChild(el('div', 'fs-5 fw-semibold', data.ticket.name));
                        detail.appendChild(el('div', 'text-muted', data.ticket.category + ' · Kode booking ' + data.ticket.booking_code + ' · ' + data.ticket.number));
                    }
                    confirmButton.classList.toggle('d-none', data.status !== 'confirm');
                    showCardActions(data);
                    if (data.stats) renderStats(data.stats);
                    addHistory(data);
                    if (data.status !== 'confirm') beep(data.status === 'ok');
                }

                function send(code, confirmed) {
                    if (busy) return;
                    busy = true;
                    fetch({{ \Illuminate\Support\Js::from(route('scan.check', request()->route('token'))) }}, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ code: code, confirm: confirmed })
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) { location.reload(); return null; }
                        return response.json();
                    }).then(function (data) {
                        if (!data) return;
                        pendingCode = data.status === 'confirm' ? code : null;
                        show(data);
                    }).catch(function () {
                        show({ status: 'error', message: 'Koneksi terputus. Coba scan lagi.' });
                    }).finally(function () {
                        busy = false;
                        input.value = '';
                        input.focus();
                    });
                }

                // Cetak / cetak ulang ID card oleh petugas (hanya untuk peserta yang sudah hadir).
                var cardBox = document.getElementById('card-actions'), cardNumber = null;
                function showCardActions(data) {
                    if (!cardBox) return;
                    var ready = data.ticket && (data.status === 'ok' || data.status === 'already');
                    cardBox.classList.toggle('d-none', !ready);
                    document.getElementById('card-message').textContent = '';
                    if (!ready) { cardNumber = null; return; }
                    cardNumber = data.ticket.number;
                    var has = !!data.ticket.has_card;
                    document.getElementById('card-hint').textContent = has ? 'Peserta ini sudah punya ID card. Cetak ulang hanya kalau kartunya rusak atau hilang.' : 'Peserta ini belum punya ID card.';
                    cardBox.querySelector('[data-card="0"] span').textContent = has ? 'Cetak ulang (QR sama)' : 'Cetak ID card';
                    cardBox.querySelector('[data-card="1"]').classList.toggle('d-none', !has);
                }
                if (cardBox) cardBox.querySelectorAll('[data-card]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var replace = button.dataset.card === '1';
                        if (!cardNumber || (replace && !confirm('Buat QR baru? Kartu lama peserta ini langsung tidak bisa dipakai absen.'))) return;
                        var message = document.getElementById('card-message');
                        message.className = 'small mt-2 text-muted'; message.textContent = 'Menyiapkan ID card…';
                        fetch({{ \Illuminate\Support\Js::from(route('scan.card', request()->route('token'))) }}, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                            body: JSON.stringify({ number: cardNumber, replace: replace })
                        }).then(function (r) { if (r.status === 401 || r.status === 419) { location.reload(); return null; } return r.json(); })
                        .then(function (data) {
                            if (!data) return;
                            message.className = 'small mt-2 ' + (data.status === 'ok' ? 'text-success' : 'text-danger');
                            message.textContent = data.message;
                            if (data.print) {
                                var frame = document.getElementById('print-frame');
                                frame.onload = function () { setTimeout(function () { frame.contentWindow.focus(); frame.contentWindow.print(); }, 300); };
                                frame.srcdoc = data.print;
                                cardBox.querySelector('[data-card="0"] span').textContent = 'Cetak ulang (QR sama)';
                                cardBox.querySelector('[data-card="1"]').classList.remove('d-none');
                            }
                        }).catch(function () { message.className = 'small mt-2 text-danger'; message.textContent = 'Koneksi terputus, coba lagi.'; });
                    });
                });

                renderStats(@json($stats));
                document.getElementById('scan-form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    var code = input.value.trim();
                    if (code) send(code, false);
                });
                confirmButton.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); });
                // Scanner USB/Bluetooth "mengetik" ke kotak input; fokus selalu dikembalikan.
                document.addEventListener('click', function (event) { if (event.target !== confirmButton && !event.target.closest('form[method=POST]') && !event.target.closest('#card-actions')) input.focus(); });
            })();
        </script>
    @endpush
@endsection
