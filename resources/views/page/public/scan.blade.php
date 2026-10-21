@extends('page.public.layout', ['open' => false])

@section('title', 'Scan · '.$page->name)
@section('width', '1040px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .scan-card { border: 0; border-radius: 1rem; }
        .scan-input-wrap { position: relative; }
        .scan-input-wrap i { position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); font-size: 1.5rem; color: var(--brand); }
        .scan-input-wrap input { padding-left: 3.25rem; border-radius: .875rem; height: 64px; font-size: 1.15rem; border: 2px solid #e9ecef; }
        .scan-input-wrap input:focus { border-color: var(--brand); box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 15%, transparent); }
        .result-card { border-radius: 1rem; border-width: 2px !important; }
        .result-icon { width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.6rem; background: #fff; }
        .gate-badge { background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); border-radius: 999px; padding: .2rem .75rem; font-size: .8125rem; font-weight: 600; }
        .stat-big { font-size: 2.75rem; font-weight: 700; line-height: 1; }
        .history-item { border-bottom: 1px solid #f1f3f5; padding: .6rem 0; }
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

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card scan-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-1">Registrasi Ulang</h2>
                    <p class="small text-muted mb-3">Arahkan scanner ke QR e-ticket, atau ketik kode booking 6 angka lalu tekan Enter.</p>
                    <form id="scan-form" autocomplete="off">
                        <div class="scan-input-wrap">
                            <i class="ri-qr-code-line"></i>
                            <input type="text" id="scan-input" class="form-control" autofocus placeholder="Scan QR atau ketik kode booking" maxlength="64">
                        </div>
                    </form>
                </div>
            </div>

            <div id="result" class="card result-card shadow-sm d-none">
                <div class="card-body text-center p-4 p-md-5">
                    <span class="result-icon mb-2"><i id="result-icon"></i></span>
                    <div id="result-message" class="fs-4 fw-bold mt-2"></div>
                    <div id="result-detail" class="mt-2"></div>
                    <button type="button" id="confirm-button" class="btn btn-success btn-lg px-5 mt-4 d-none"><i class="ri-check-line me-1"></i>Konfirmasi Hadir</button>
                </div>
            </div>
            <div id="result-empty" class="card scan-card shadow-sm">
                <div class="card-body text-center text-muted p-5">
                    <i class="ri-scan-line fs-1 d-block mb-2"></i>Hasil scan akan tampil di sini.
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card scan-card shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="small text-muted mb-1">Sudah hadir</div>
                    <div class="d-flex align-items-end gap-2 mb-2">
                        <span class="stat-big" id="stat-in">{{ $stats['checked_in'] }}</span>
                        <span class="text-muted mb-1">/ <span id="stat-total">{{ $stats['total'] }}</span> tiket</span>
                    </div>
                    <div class="progress mb-4" style="height: 10px;"><div class="progress-bar" id="stat-bar" style="background: var(--brand); width: 0%;"></div></div>
                    <div id="stat-categories" class="small"></div>
                </div>
            </div>

            <div class="card scan-card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-2">Scan terakhir</h2>
                    <div id="history" class="small"></div>
                    <div id="history-empty" class="small text-muted">Belum ada scan di perangkat ini.</div>
                </div>
            </div>
        </div>
    </div>

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
                        var head = el('div', 'd-flex justify-content-between mb-1');
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
                    history = history.slice(0, 6);
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
                }

                function show(data) {
                    var style = styles[data.status] || styles.error;
                    document.getElementById('result-empty').classList.add('d-none');
                    result.className = 'card result-card shadow-sm border ' + style[0];
                    document.getElementById('result-icon').className = style[1];
                    document.getElementById('result-message').textContent = data.message;
                    var detail = document.getElementById('result-detail');
                    detail.innerHTML = '';
                    if (data.ticket) {
                        detail.appendChild(el('div', 'fs-5 fw-semibold', data.ticket.name));
                        detail.appendChild(el('div', 'text-muted', data.ticket.category + ' · Kode booking ' + data.ticket.booking_code + ' · ' + data.ticket.number));
                    }
                    confirmButton.classList.toggle('d-none', data.status !== 'confirm');
                    if (data.stats) renderStats(data.stats);
                    addHistory(data);
                    if (data.status !== 'confirm') beep(data.status === 'ok');
                }

                function send(code, confirmed) {
                    if (busy) return;
                    busy = true;
                    fetch(@json(route('scan.check', request()->route('token'))), {
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

                renderStats(@json($stats));
                document.getElementById('scan-form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    var code = input.value.trim();
                    if (code) send(code, false);
                });
                confirmButton.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); });
                // Scanner USB/Bluetooth "mengetik" ke kotak input; fokus selalu dikembalikan.
                document.addEventListener('click', function (event) { if (event.target !== confirmButton && !event.target.closest('form[method=POST]')) input.focus(); });
            })();
        </script>
    @endpush
@endsection
