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
        .live-dot.is-wait { background: #f59e0b; animation: none; }
        .live-dot.is-off { background: #dc3545; animation: none; }
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
            <form method="POST" action="{{ route('scan.logout', request()->route('token')) }}" id="logout-form">
                @csrf
                <button type="submit" class="btn btn-sm btn-light"><i class="ri-logout-box-r-line"></i> Keluar</button>
            </form>
        </div>
    </div>

    {{-- Di HP kotak scan tampil paling atas (kamera langsung terjangkau); di layar besar tetap di bawah. --}}
    <div class="d-flex flex-column">
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card scan-card scan-box">
                <div class="scan-box-head"><span class="icon"><i class="ri-notification-3-line"></i></span>Notifikasi</div>
                <div class="scan-box-body">
                    <div id="result" class="scan-result border d-none">
                        <span class="result-icon mb-2"><i id="result-icon"></i></span>
                        <div id="result-message" class="fs-5 fw-bold mt-2"></div>
                        <div id="result-detail" class="mt-2 small"></div>
                        <button type="button" id="confirm-button" class="btn btn-success px-4 mt-3 d-none"><i class="ri-check-line me-1"></i>Konfirmasi Hadir <span class="opacity-75 small">(Enter)</span></button>
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

    <div class="card scan-card order-first order-lg-last mb-4 mb-lg-0">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                <h2 class="h5 fw-bold mb-0">Registrasi Ulang</h2>
                {{-- Status mode lapangan: data tiket di HP & antrean scan yang belum terkirim. --}}
                <span class="small text-muted d-inline-flex align-items-center gap-2" role="status" aria-live="polite"><span class="live-dot is-wait" id="field-dot"></span><span id="field-text">Menyiapkan data tiket…</span></span>
            </div>
            <p class="small text-muted mb-3">Arahkan alat scan atau kamera HP ke QR e-ticket atau QR ID card, langsung terkirim otomatis tanpa tekan Enter. QR rusak? Ketik kode booking 6 angka. Sinyal macet atau putus? Scan tetap jalan, hasilnya terkirim sendiri saat sinyal kembali.</p>
            @include('schedule._qr-scan', ['onScan' => 'scanFromCamera', 'title' => 'Scan pakai kamera HP', 'hint' => 'Buka kamera sekali, lalu arahkan ke QR tiket satu per satu. Hasilnya muncul besar di layar.'])
            <form id="scan-form" autocomplete="off">
                <div class="scan-input-wrap">
                    <i class="ri-qr-code-line"></i>
                    <input type="text" id="scan-input" class="form-control" autofocus placeholder="Scan QR atau ketik kode booking" maxlength="64">
                </div>
            </form>
        </div>
    </div>
    </div>

    <iframe id="print-frame" title="Cetak ID card" style="position: absolute; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

    @push('scripts')
        <script src="{{ asset('be/assets/libs/sweetalert2/sweetalert2.all.min.js') }}?v=11.26.3"></script>
        <script>
            (function () {
                var input = document.getElementById('scan-input');
                // Di HP (layar sentuh) kotak ketik tidak difokuskan otomatis, supaya keyboard tidak terus muncul saat memakai kamera.
                var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
                function focusInput() { if (!touch) input.focus(); }
                if (touch) input.blur();
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
                    if (navigator.vibrate) navigator.vibrate(data.status === 'ok' ? 120 : [250, 120, 250]);
                    popup(data);
                }

                // Hasil scan dalam pop-up besar supaya jelas terlihat di lapangan. Berhasil tertutup sendiri 2 detik,
                // peringatan 4 detik; perlu konfirmasi menunggu petugas memilih. Ketuk di mana saja untuk menutup.
                var popupStyle = {
                    ok: ['success', 2000], already: ['warning', 4000], not_found: ['error', 4000], error: ['error', 4000], confirm: ['question', null]
                };
                function popup(data) {
                    if (!window.Swal) return;
                    var style = popupStyle[data.status] || popupStyle.error, asking = data.status === 'confirm';
                    var body = el('div');
                    if (data.ticket) {
                        body.appendChild(el('div', 'fs-3 fw-bold text-body', data.ticket.name));
                        body.appendChild(el('div', 'text-muted mt-1', data.ticket.category + ' · Kode booking ' + data.ticket.booking_code));
                    }
                    Swal.fire({
                        icon: style[0], title: data.message, html: body,
                        timer: style[1] || undefined, timerProgressBar: !!style[1],
                        showConfirmButton: asking, showCancelButton: asking,
                        confirmButtonText: 'Konfirmasi Hadir', cancelButtonText: 'Batal',
                        confirmButtonColor: '#198754', focusConfirm: false, returnFocus: false, heightAuto: false,
                        // Alat scan tetap bisa langsung mengetik ke kotak scan selama pop-up tampil (Enter = Konfirmasi Hadir).
                        didOpen: focusInput,
                        didClose: focusInput
                    }).then(function (answer) {
                        if (asking && answer.isConfirmed && pendingCode) send(pendingCode, true);
                    });
                }

                // Kamera HP: kode yang sama tidak dikirim ulang selama masih di depan kamera (8 detik),
                // dan diabaikan selama hasil sebelumnya masih tampil.
                var lastCamera = '', lastCameraAt = 0;
                window.scanFromCamera = function (code) {
                    code = String(code).trim();
                    if (!code || busy || (window.Swal && Swal.isVisible())) return;
                    if (code === lastCamera && Date.now() - lastCameraAt < 8000) return;
                    lastCamera = code; lastCameraAt = Date.now();
                    send(code, false);
                };

                var csrf = function () { return document.querySelector('meta[name=csrf-token]').content; };
                var urls = {
                    check: {{ \Illuminate\Support\Js::from(route('scan.check', request()->route('token'))) }},
                    tickets: {{ \Illuminate\Support\Js::from(route('scan.tickets', request()->route('token'))) }},
                    sync: {{ \Illuminate\Support\Js::from(route('scan.sync', request()->route('token'))) }}
                };

                // Permintaan ke server dengan batas waktu: di jaringan event yang macet, lebih baik cepat menyerah lalu coba lagi.
                function request(url, body, ms) {
                    var ctrl = window.AbortController ? new AbortController() : null, timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, ms || 8000);
                    return fetch(url, {
                        method: body ? 'POST' : 'GET', signal: ctrl ? ctrl.signal : undefined,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                        body: body ? JSON.stringify(body) : undefined
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) throw 'login';
                        if (!response.ok) throw 'http';
                        return response.json();
                    }).finally(function () { clearTimeout(timer); });
                }

                /*
                 * Mode lapangan (jaringan event sering macet/putus, puluhan ribu tiket, banyak gate):
                 * - Sinyal lancar: scan dicek server dulu (maks. 1,5 detik) supaya tiket yang sama tidak bisa lolos di dua gate.
                 * - Lambat/putus: scan diputuskan di HP dari data tiket yang sudah diunduh, lalu masuk antrean yang dikirim di belakang layar.
                 *   Server memeriksa ulang dengan aturan & kunci yang sama; tiket yang ternyata sudah masuk di gate lain diberi tahu ke petugas.
                 * Data tiket (besar) disimpan di IndexedDB; antrean (kecil, paling penting) di localStorage dan disimpan setiap scan.
                 * QR di data HP hanya berupa hash, jadi tidak bisa dipakai membuat tiket.
                 * Baris tiket: [hash QR e-ticket, hash QR ID card, kode booking, nama, kategori, jam masuk, petugas, punya ID card].
                 */
                var me = {{ \Illuminate\Support\Js::from($scanner->name) }}, storeKey = 'bb-scan:' + {{ \Illuminate\Support\Js::from($scanner->getKey()) }};
                var field = {
                    supported: !!(window.crypto && crypto.subtle && window.TextEncoder),
                    data: null, queue: read(storeKey + ':queue') || [], index: {},
                    ready: false, online: true, slowUntil: 0, needPin: false, sending: false, refreshing: false
                };

                function read(key) { try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; } }
                function saveQueue() { try { localStorage.setItem(storeKey + ':queue', JSON.stringify(field.queue)); } catch (e) {} }

                // IndexedDB sederhana (satu kunci = seluruh data tiket). Tidak tersedia? Data tetap dipakai selama halaman terbuka.
                function idb(mode, work) {
                    return new Promise(function (resolve, reject) {
                        if (!window.indexedDB) return reject('none');
                        var open = indexedDB.open('bb-scan', 1);
                        open.onupgradeneeded = function () { open.result.createObjectStore('kv'); };
                        open.onerror = function () { reject(open.error); };
                        open.onsuccess = function () {
                            var request = work(open.result.transaction('kv', mode).objectStore('kv'));
                            request.onsuccess = function () { resolve(request.result); };
                            request.onerror = function () { reject(request.error); };
                        };
                    });
                }
                function saveData() { return idb('readwrite', function (store) { return store.put(field.data, storeKey); }).catch(function () {}); }

                function sha(text) {
                    return crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)).then(function (buffer) {
                        return Array.prototype.map.call(new Uint8Array(buffer), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('').slice(0, 16);
                    });
                }

                // Scan di antrean (belum sampai server) ditandai di data tiket, supaya tetap terhitung setelah halaman dibuka ulang.
                function applyQueue() {
                    field.queue.forEach(function (item) {
                        findTicket(item.code).then(function (hit) {
                            if (hit && hit !== 'invalid' && !hit.row[5]) { hit.row[5] = item.at; hit.row[6] = me; }
                        });
                    });
                }

                function reindex() {
                    field.index = {};
                    Object.keys(field.data.tickets).forEach(function (number) {
                        var row = field.data.tickets[number];
                        field.index[row[0]] = number;
                        if (row[1]) field.index[row[1]] = number;
                        field.index['b' + row[2]] = number;
                    });
                }

                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                function when(iso) {
                    var d = new Date(iso), pad = function (n) { return ('0' + n).slice(-2); };
                    return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ', ' + pad(d.getDate()) + ' ' + months[d.getMonth()];
                }

                function summary(number, row) {
                    return { name: row[3], category: row[4], booking_code: row[2], number: number, has_card: !!row[7] };
                }

                function localStats() {
                    var categories = {}, stats = { total: 0, checked_in: 0, categories: [] };
                    Object.keys(field.data.tickets).forEach(function (number) {
                        var row = field.data.tickets[number], c = categories[row[4]] || (categories[row[4]] = { name: row[4], total: 0, checked_in: 0 });
                        stats.total++; c.total++;
                        if (row[5]) { stats.checked_in++; c.checked_in++; }
                    });
                    stats.categories = Object.keys(categories).sort().map(function (name) { return categories[name]; });
                    return stats;
                }

                // Cari tiket di data HP. QR (48 karakter) dicocokkan lewat hash; kode booking & no. tiket apa adanya.
                function findTicket(code) {
                    var wrap = function (number, byQr) { return number ? { number: number, row: field.data.tickets[number], byQr: byQr } : null; };
                    if (/^[A-Za-z0-9]{48}$/.test(code)) return sha(field.data.salt + code).then(function (hash) { return wrap(field.index[hash], true); });
                    if (/^\d{6}$/.test(code)) return Promise.resolve(wrap(field.index['b' + code], false));
                    if (/^BB\d{8}$/i.test(code)) return Promise.resolve(wrap(field.data.tickets[code.toUpperCase()] ? code.toUpperCase() : null, false));
                    return Promise.resolve('invalid');
                }

                function localScan(code, confirmed) {
                    return findTicket(code).then(function (hit) {
                        if (hit === 'invalid') return show({ status: 'not_found', message: 'Kode tidak dikenali. Scan QR, atau ketik kode booking 6 angka.' });
                        // Tidak ada di data HP (mis. tiket baru terjual): tanya server langsung.
                        if (!hit) return serverScan(code, confirmed, 'Tiket tidak ada di data HP dan server belum terjangkau. Minta peserta menunggu sebentar, lalu scan lagi.');
                        var row = hit.row, ticket = summary(hit.number, row);
                        if (row[5]) return show({ status: 'already', message: 'Tiket ini sudah discan pukul ' + when(row[5]) + (row[6] ? ' oleh ' + row[6] : '') + '.', ticket: ticket });
                        if (!hit.byQr && !confirmed) {
                            pendingCode = code;
                            return show({ status: 'confirm', message: 'Cocokkan nama dengan pemegang tiket, lalu konfirmasi.', ticket: ticket });
                        }
                        var at = new Date().toISOString();
                        pendingCode = null;
                        row[5] = at; row[6] = me;
                        field.queue.push({ code: code, at: at, confirm: !hit.byQr }); saveQueue();
                        status();
                        show({ status: 'ok', message: 'Selamat datang, ' + row[3] + '!', ticket: ticket, stats: localStats() });
                        setTimeout(flush, 50);
                    });
                }

                // Cek ke server (sumber kebenaran). Hasil "berhasil" ikut ditandai di data HP.
                function serverCheck(code, confirmed, ms) {
                    return request(urls.check, { code: code, confirm: confirmed, lite: field.ready }, ms).then(function (data) {
                        pendingCode = data.status === 'confirm' ? code : null;
                        var row = data.ticket && field.data && field.data.tickets[data.ticket.number];
                        if (row && (data.status === 'ok' || data.status === 'already') && !row[5]) { row[5] = new Date().toISOString(); row[6] = data.status === 'ok' ? me : null; }
                        field.online = true;
                        show(data);
                    });
                }

                // Langsung ke server saja: sebelum data tiket siap, atau tiket yang belum ada di data HP.
                function serverScan(code, confirmed, offlineMessage) {
                    return serverCheck(code, confirmed, 8000).catch(function (error) {
                        if (error === 'login') { field.needPin = true; status(); }
                        show({ status: 'error', message: error === 'login' ? 'Sesi berakhir. Tekan "Masukkan PIN lagi" di atas.' : (offlineMessage || 'Koneksi terputus. Coba scan lagi.') });
                    });
                }

                // Sinyal lancar: server dulu (maks. 1,5 detik). Lambat/putus/kena batas: data HP, dan selama 30 detik ke depan
                // langsung pakai data HP supaya antrean tidak tertahan menunggu jaringan yang sedang macet.
                function fastScan(code, confirmed) {
                    return serverCheck(code, confirmed, 1500).catch(function (error) {
                        if (error === 'login') { field.needPin = true; status(); }
                        else { field.slowUntil = Date.now() + 30000; field.online = false; status(); }
                        return localScan(code, confirmed);
                    });
                }

                function send(code, confirmed) {
                    if (busy) return;
                    busy = true;
                    if (!confirmed) pendingCode = null;
                    var job = !field.ready ? serverScan(code, confirmed)
                        : (field.online && !field.needPin && Date.now() > field.slowUntil ? fastScan(code, confirmed) : localScan(code, confirmed));
                    job.finally(function () {
                        busy = false;
                        input.value = '';
                        focusInput();
                    });
                }

                // Kirim antrean (maks. 50 per kiriman). Gagal = dicoba lagi otomatis; antrean tetap tersimpan walau halaman tertutup.
                function flush() {
                    if (field.sending || field.needPin || !field.queue.length) return;
                    field.sending = true;
                    var batch = field.queue.slice(0, 50);
                    request(urls.sync, { items: batch }, 15000).then(function (data) {
                        field.queue.splice(0, batch.length); saveQueue();
                        field.online = true;
                        // Diloloskan HP, ternyata server menolak (mis. sudah masuk di gate lain sebelumnya): beri tahu petugas.
                        data.results.forEach(function (res) {
                            if (res.status !== 'ok') notice({ status: 'already', message: 'Dicek ulang server: ' + res.message, ticket: res.ticket });
                        });
                    }).catch(function (error) {
                        if (error === 'login') field.needPin = true;
                        field.online = false;
                    }).finally(function () {
                        field.sending = false;
                        status();
                        if (field.queue.length && field.online && !field.needPin) setTimeout(flush, 300);
                    });
                }

                // Hasil "dicek ulang" dari server: masuk riwayat; pop-up hanya kalau tidak sedang menampilkan hasil scan lain.
                function notice(data) {
                    addHistory(data);
                    beep(false);
                    if (window.Swal && !Swal.isVisible()) popup(data);
                }

                // Unduh data tiket: lengkap (pertama kali / tiap 15 menit) atau hanya yang berubah sejak terakhir.
                function refresh() {
                    if (!field.supported || field.refreshing || field.needPin) return;
                    // Unduh lengkap hanya pertama kali & tiap 1 jam (tiket yang dihapus/dibatalkan); selebihnya hanya yang berubah.
                    var full = !field.data || !field.data.full_at || Date.now() - new Date(field.data.full_at).getTime() > 60 * 60 * 1000;
                    field.refreshing = true;
                    request(urls.tickets + (full ? '' : '?since=' + encodeURIComponent(field.data.synced)), null, full ? 60000 : 15000).then(function (data) {
                        // Kunci data berubah (jarang): ambil ulang lengkap.
                        if (!data.full && field.data.salt !== data.salt) { field.data.full_at = null; return; }
                        var old = field.data && field.data.salt === data.salt ? field.data.tickets : {};
                        var tickets = data.full ? {} : Object.assign({}, old);
                        Object.keys(data.tickets).forEach(function (number) {
                            var row = data.tickets[number], mine = old[number];
                            // Scan di HP ini yang belum sampai server tetap dipertahankan.
                            if (mine && mine[5] && !row[5]) { row[5] = mine[5]; row[6] = mine[6]; }
                            tickets[number] = row;
                        });
                        field.data = { salt: data.salt, synced: data.server_time, full_at: data.full ? data.server_time : field.data.full_at, tickets: tickets };
                        reindex(); saveData();
                        field.ready = true; field.online = true;
                        renderStats(localStats());
                    }).catch(function (error) {
                        if (error === 'login') field.needPin = true;
                        field.online = false;
                    }).finally(function () {
                        field.refreshing = false;
                        status();
                    });
                }

                var dot = document.getElementById('field-dot'), statusText = document.getElementById('field-text');
                function status() {
                    var waiting = field.queue.length, count = field.ready ? Object.keys(field.data.tickets).length : 0;
                    statusText.textContent = '';
                    if (field.needPin) {
                        dot.className = 'live-dot is-off';
                        statusText.appendChild(document.createTextNode('Sesi berakhir' + (waiting ? ', ' + waiting + ' scan menunggu. ' : '. ')));
                        var again = el('a', 'fw-semibold', 'Masukkan PIN lagi'); again.href = location.href;
                        statusText.appendChild(again);
                    } else if (!field.ready) {
                        dot.className = 'live-dot is-wait';
                        statusText.textContent = field.supported ? 'Menyiapkan data tiket…' : 'Mode online (browser ini tidak mendukung mode lapangan)';
                    } else if (waiting) {
                        dot.className = 'live-dot is-wait';
                        statusText.textContent = waiting + ' scan belum terkirim · ' + (field.online ? 'mengirim…' : 'menunggu sinyal');
                    } else {
                        dot.className = 'live-dot' + (field.online ? '' : ' is-wait');
                        statusText.textContent = 'Siap · ' + count.toLocaleString('id-ID') + ' tiket di HP' + (field.online ? '' : ' · offline, scan tetap jalan');
                    }
                }

                // Data tiket dari kunjungan sebelumnya langsung dipakai, lalu diperbarui di belakang layar.
                status();
                (field.supported ? idb('readonly', function (store) { return store.get(storeKey); }) : Promise.reject('none')).then(function (data) {
                    if (data && data.tickets && !field.ready) {
                        field.data = data; reindex(); applyQueue(); field.ready = true;
                        renderStats(localStats()); status();
                    }
                }).catch(function () {}).finally(refresh);
                var ticks = 0;
                setInterval(function () { flush(); if (++ticks % 4 === 0) refresh(); }, 5000);
                window.addEventListener('online', function () { field.online = true; flush(); refresh(); });

                // Jangan sampai antrean hilang: keluar ditahan selama masih ada scan yang belum terkirim.
                document.getElementById('logout-form').addEventListener('submit', function (event) {
                    if (field.queue.length) {
                        event.preventDefault();
                        if (window.Swal) Swal.fire({ icon: 'warning', title: 'Masih ada ' + field.queue.length + ' scan belum terkirim', text: 'Tunggu sinyal sampai semuanya terkirim, baru keluar. Kalau keluar sekarang, data scan ini hilang.', heightAuto: false });
                        return;
                    }
                    try { localStorage.removeItem(storeKey + ':queue'); } catch (e) {}
                    idb('readwrite', function (store) { return store.delete(storeKey); }).catch(function () {});
                });
                window.addEventListener('beforeunload', function (event) {
                    if (field.queue.length && !field.online) { event.preventDefault(); event.returnValue = ''; }
                });

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
                    // Kolom kosong + ada yang menunggu konfirmasi: Enter di keyboard = Konfirmasi Hadir (tanpa mouse).
                    if (code) send(code, false);
                    else if (pendingCode) send(pendingCode, true);
                });
                confirmButton.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); });
                // Scanner USB/Bluetooth "mengetik" ke kotak input; fokus selalu dikembalikan.
                document.addEventListener('click', function (event) { if (event.target !== confirmButton && !event.target.closest('form[method=POST]') && !event.target.closest('#card-actions') && !event.target.closest('#qr-scan') && !event.target.closest('.swal2-container')) focusInput(); });
            })();
        </script>
        <script src="{{ asset('be/assets/js/scan-autosubmit.js') }}"></script>
    @endpush
@endsection
