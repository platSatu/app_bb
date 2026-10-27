@extends('page.public.layout', ['open' => false])

@section('title', 'Absensi · '.$page->name)
@section('width', '1080px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Warna halaman mengikuti warna tiket, supaya serasi dengan e-ticket & ID card. --}}
    @if ($page->setting('ticket.color'))
        <style>:root:root { --brand: {{ $page->setting('ticket.color') }}; }</style>
    @endif
    <style>
        .scan-card { border: 0; border-radius: 1rem; }
        .session-pick { cursor: pointer; border: 2px solid #eef0f3; border-radius: 1rem; padding: .85rem 1rem; background: #fff; transition: border-color .15s, box-shadow .15s; text-align: left; width: 100%; }
        .session-pick:hover { border-color: #d7dbe0; }
        .session-pick.is-selected { border-color: var(--brand); box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 12%, transparent); }
        .state-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: .35rem; }
        .scan-input-wrap { position: relative; }
        .scan-input-wrap i { position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); font-size: 1.5rem; color: var(--brand); }
        .scan-input-wrap input { padding-left: 3.25rem; border-radius: .875rem; height: 64px; font-size: 1.15rem; border: 2px solid #e9ecef; }
        .scan-input-wrap input:focus { border-color: var(--brand); box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 15%, transparent); }
        .scan-input-wrap input:disabled { background: #f6f7f9; }
        .result-card { border-radius: 1rem; border-width: 2px !important; }
        .result-icon { width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.6rem; background: #fff; }
        .gate-badge { background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); border-radius: 999px; padding: .2rem .75rem; font-size: .8125rem; font-weight: 600; }
        .stat-big { font-size: 2.75rem; font-weight: 700; line-height: 1; }
        .history-item { border-bottom: 1px solid #f1f3f5; padding: .6rem 0; }
        .history-item:last-child { border-bottom: 0; }
        .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: .4rem; }
        .clock { font-variant-numeric: tabular-nums; }
    </style>
@endpush

@section('content')
    <div class="card scan-card shadow-sm mb-4">
        <div class="card-body p-3 px-md-4 d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <span class="result-icon flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.5rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand);"><i class="ri-calendar-check-line"></i></span>
                <div class="min-w-0">
                    <div class="fw-bold text-truncate">{{ $page->name }}</div>
                    <span class="gate-badge"><i class="ri-user-location-line me-1"></i>Absensi · {{ $scanner->name }}</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="fw-semibold fs-5 clock d-none d-sm-inline" id="clock"></span>
                <form method="POST" action="{{ route('scan.logout', request()->route('token')) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light"><i class="ri-logout-box-r-line"></i> Keluar</button>
                </form>
            </div>
        </div>
    </div>

    @if ($sessions->isEmpty())
        <div class="card scan-card shadow-sm">
            <div class="card-body text-center text-muted p-5">
                <i class="ri-calendar-event-line fs-1 d-block mb-2"></i>
                Belum ada sesi yang aktif untuk acara ini. Minta penyelenggara menambahkan jadwalnya dulu ya.
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card scan-card shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-1">1. Pilih sesi yang sedang Anda jaga</h2>
                        <p class="small text-muted mb-3">Status buka/tutupnya berubah otomatis sesuai jadwal.</p>
                        <div class="row g-2" id="session-list">
                            @foreach ($sessions as $session)
                                <div class="col-md-6">
                                    <button type="button" class="session-pick" data-session="{{ $session->getKey() }}" data-name="{{ $session->name }}"
                                        data-opens="{{ $session->opensAt()->toIso8601String() }}" data-closes="{{ $session->closesAt()->toIso8601String() }}"
                                        data-attended="{{ $stats[$session->getKey()]['attended'] }}" data-eligible="{{ $stats[$session->getKey()]['eligible'] }}">
                                        <div class="fw-semibold text-truncate">{{ $session->name }}</div>
                                        <div class="small text-muted text-truncate">{{ $session->starts_at->translatedFormat('D, d M · H:i') }}–{{ $session->ends_at->format('H:i') }}{{ $session->room ? ' · '.$session->room : '' }}</div>
                                        <div class="small mt-1" data-state></div>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card scan-card shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-1">2. Scan ID card peserta</h2>
                        <p class="small text-muted mb-3" id="scan-hint">Arahkan scanner ke QR di ID card. Langsung terkirim otomatis. QR rusak? Ketik kode booking 6 angka.</p>
                        <form id="scan-form" autocomplete="off">
                            <div class="scan-input-wrap">
                                <i class="ri-id-card-line"></i>
                                <input type="text" id="scan-input" class="form-control" placeholder="Scan QR ID card atau ketik kode booking" maxlength="64">
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
                    <div class="card-body text-center text-muted p-5"><i class="ri-scan-line fs-1 d-block mb-2"></i>Hasil scan akan tampil di sini.</div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card scan-card shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="small text-muted mb-1" id="stat-title">Sudah hadir</div>
                        <div class="d-flex align-items-end gap-2 mb-2">
                            <span class="stat-big" id="stat-in">0</span>
                            <span class="text-muted mb-1">/ <span id="stat-total">0</span> peserta</span>
                        </div>
                        <div class="progress" style="height: 10px;"><div class="progress-bar" id="stat-bar" style="background: var(--brand); width: 0%;"></div></div>
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
    @endif

    @push('scripts')
        <script>
            (function () {
                var list = document.getElementById('session-list');
                if (!list) return;
                // Selisih jam server vs perangkat, supaya status buka/tutup tidak tertipu jam HP yang salah.
                var offset = new Date({{ \Illuminate\Support\Js::from(now()->toIso8601String()) }}).getTime() - Date.now();
                var input = document.getElementById('scan-input');
                var result = document.getElementById('result');
                var confirmButton = document.getElementById('confirm-button');
                var picks = list.querySelectorAll('[data-session]');
                var selected = null, pendingCode = null, busy = false, history = [];
                var styles = {
                    ok: ['border-success bg-success-subtle', 'ri-checkbox-circle-fill text-success', '#198754'],
                    already: ['border-warning bg-warning-subtle', 'ri-error-warning-fill text-warning', '#ffc107'],
                    confirm: ['border-primary bg-primary-subtle', 'ri-user-search-line text-primary', '#0d6efd'],
                    closed: ['border-secondary bg-light', 'ri-time-line text-secondary', '#6c757d'],
                    wrong_card: ['border-info bg-info-subtle', 'ri-id-card-line text-info', '#0dcaf0'],
                    not_allowed: ['border-danger bg-danger-subtle', 'ri-forbid-line text-danger', '#dc3545'],
                    not_found: ['border-danger bg-danger-subtle', 'ri-close-circle-fill text-danger', '#dc3545'],
                    error: ['border-danger bg-danger-subtle', 'ri-wifi-off-line text-danger', '#dc3545']
                };

                function now() { return new Date(Date.now() + offset); }
                // Jam selalu ditampilkan dalam zona waktu acara, walau setelan HP petugas berbeda.
                var tz = {{ \Illuminate\Support\Js::from(config('app.timezone')) }};
                function hm(d) { return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: tz }); }
                function hms(d) { return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: tz }); }
                function el(tag, className, text) { var n = document.createElement(tag); if (className) n.className = className; if (text !== undefined) n.textContent = text; return n; }
                function stateOf(b) {
                    var t = now(), o = new Date(b.dataset.opens), c = new Date(b.dataset.closes);
                    if (t < o) return ['upcoming', '#0dcaf0', 'Dibuka ' + hm(o)];
                    if (t > c) return ['closed', '#adb5bd', 'Ditutup ' + hm(c)];
                    return ['open', '#198754', 'Dibuka sampai ' + hm(c)];
                }

                function beep(ok) {
                    try { var ctx = new (window.AudioContext || window.webkitAudioContext)(), osc = ctx.createOscillator(); osc.frequency.value = ok ? 880 : 220; osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.15); } catch (e) {}
                }

                function renderStats() {
                    if (!selected) return;
                    var a = +selected.dataset.attended, e = +selected.dataset.eligible;
                    document.getElementById('stat-title').textContent = 'Sudah hadir di ' + selected.dataset.name;
                    document.getElementById('stat-in').textContent = a;
                    document.getElementById('stat-total').textContent = e;
                    document.getElementById('stat-bar').style.width = (e ? a / e * 100 : 0) + '%';
                }

                function tick() {
                    document.getElementById('clock').textContent = hms(now());
                    picks.forEach(function (b) {
                        var s = stateOf(b), box = b.querySelector('[data-state]');
                        box.innerHTML = '';
                        var dot = el('span', 'state-dot'); dot.style.background = s[1];
                        box.appendChild(dot); box.appendChild(document.createTextNode(s[2]));
                    });
                    if (selected) {
                        var s = stateOf(selected), open = s[0] === 'open';
                        input.disabled = !open;
                        input.placeholder = open ? 'Scan QR ID card atau ketik kode booking' : (s[0] === 'upcoming' ? 'Absensi ' + selected.dataset.name + ' belum dibuka (' + s[2].toLowerCase() + ')' : 'Absensi ' + selected.dataset.name + ' sudah ditutup');
                    }
                }

                function select(b) {
                    selected = b;
                    picks.forEach(function (p) { p.classList.toggle('is-selected', p === b); });
                    pendingCode = null;
                    confirmButton.classList.add('d-none');
                    try { localStorage.setItem('attendance-session-{{ $scanner->getKey() }}', b.dataset.session); } catch (e) {}
                    renderStats(); tick(); input.focus();
                }

                function addHistory(data) {
                    if (data.status === 'confirm') return;
                    history.unshift({ color: (styles[data.status] || styles.error)[2], title: data.ticket ? data.ticket.name : data.message, detail: data.ticket ? data.ticket.category + ' · ' + data.message : '', time: hms(now()) });
                    history = history.slice(0, 6);
                    var box = document.getElementById('history'); box.innerHTML = '';
                    history.forEach(function (h) {
                        var row = el('div', 'history-item d-flex gap-2'), dot = el('span', 'dot'), body = el('div', 'flex-grow-1 min-w-0'), top = el('div', 'd-flex justify-content-between gap-2');
                        dot.style.background = h.color;
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
                    var detail = document.getElementById('result-detail'); detail.innerHTML = '';
                    if (data.ticket) {
                        detail.appendChild(el('div', 'fs-5 fw-semibold', data.ticket.name));
                        detail.appendChild(el('div', 'text-muted', data.ticket.category + ' · Kode booking ' + data.ticket.booking_code));
                    }
                    confirmButton.classList.toggle('d-none', data.status !== 'confirm');
                    if (data.stats && selected) { selected.dataset.attended = data.stats.attended; selected.dataset.eligible = data.stats.eligible; renderStats(); }
                    addHistory(data);
                    if (data.status !== 'confirm') beep(data.status === 'ok');
                }

                function send(code, confirmed) {
                    if (busy || !selected) return;
                    busy = true;
                    fetch({{ \Illuminate\Support\Js::from(route('scan.check', request()->route('token'))) }}, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ code: code, confirm: confirmed, session_id: selected.dataset.session })
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) { location.reload(); return null; }
                        return response.json();
                    }).then(function (data) {
                        if (!data) return;
                        pendingCode = data.status === 'confirm' ? code : null;
                        show(data);
                    }).catch(function () {
                        show({ status: 'error', message: 'Koneksi terputus. Coba scan lagi.' });
                    }).finally(function () { busy = false; input.value = ''; input.focus(); });
                }

                picks.forEach(function (b) { b.addEventListener('click', function () { select(b); }); });
                document.getElementById('scan-form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    var code = input.value.trim();
                    if (code) send(code, false);
                });
                confirmButton.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); });
                document.addEventListener('click', function (event) { if (!event.target.closest('button, form[method=POST]')) input.focus(); });

                // Pilih otomatis: sesi terakhir di perangkat ini, lalu sesi yang sedang dibuka, lalu yang paling dekat.
                var remembered = null;
                try { remembered = localStorage.getItem('attendance-session-{{ $scanner->getKey() }}'); } catch (e) {}
                var initial = Array.prototype.find.call(picks, function (b) { return b.dataset.session === remembered && stateOf(b)[0] !== 'closed'; })
                    || Array.prototype.find.call(picks, function (b) { return stateOf(b)[0] === 'open'; })
                    || Array.prototype.find.call(picks, function (b) { return stateOf(b)[0] === 'upcoming'; })
                    || picks[picks.length - 1];
                select(initial);
                setInterval(tick, 1000);
            })();
        </script>
        <script src="{{ asset('be/assets/js/scan-autosubmit.js') }}"></script>
    @endpush
@endsection
