@extends('page.public.layout', ['open' => false])

@section('title', 'Cetak ID Card · '.$page->name)
@section('width', '1120px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Warna halaman mengikuti warna tiket, supaya serasi dengan e-ticket & ID card. --}}
    @if ($page->setting('ticket.color'))
        <style>:root:root { --brand: {{ $page->setting('ticket.color') }}; }</style>
    @endif
    <style>
        .kiosk { min-height: calc(100vh - 7rem); display: flex; flex-direction: column; }
        .kiosk-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; }
        .kiosk-brand { display: flex; align-items: center; gap: .75rem; min-width: 0; }
        .kiosk-brand img { height: 44px; max-width: 140px; object-fit: contain; }
        .kiosk-panel { flex: 1; border: 0; border-radius: 1.75rem; box-shadow: 0 20px 50px -20px rgba(16,24,40,.25); overflow: hidden; background: #fff; }
        .kiosk-screen { display: none; min-height: 520px; padding: 3rem 2rem; animation: kiosk-in .35s ease; }
        .kiosk-screen.is-active { display: flex; }
        @keyframes kiosk-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .kiosk-hello { font-size: clamp(2rem, 4vw, 3.1rem); font-weight: 700; line-height: 1.1; }
        .kiosk-sub { font-size: clamp(1.05rem, 1.7vw, 1.35rem); color: #6b7280; }
        .kiosk-qr-pulse { width: 168px; height: 168px; border-radius: 2rem; display: flex; align-items: center; justify-content: center; font-size: 5.5rem; color: var(--brand); background: color-mix(in srgb, var(--brand) 10%, #fff); position: relative; }
        .kiosk-qr-pulse::after { content: ''; position: absolute; inset: -10px; border-radius: 2.4rem; border: 3px solid color-mix(in srgb, var(--brand) 35%, transparent); animation: kiosk-pulse 1.8s ease-out infinite; }
        @keyframes kiosk-pulse { from { opacity: 1; transform: scale(.94); } to { opacity: 0; transform: scale(1.12); } }
        .kiosk-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 2.5rem; width: 100%; max-width: 760px; }
        .kiosk-step { background: #f8f9fb; border-radius: 1.25rem; padding: 1.25rem 1rem; text-align: center; }
        .kiosk-step i { font-size: 2rem; color: var(--brand); }
        .kiosk-step b { display: block; margin-top: .35rem; }
        .kiosk-step span { color: #6b7280; font-size: .9rem; }
        .kiosk-btn { font-size: 1.25rem; font-weight: 600; padding: 1rem 2.25rem; border-radius: 1rem; }
        .kiosk-card-stage { background: repeating-linear-gradient(45deg, #f6f7f9, #f6f7f9 14px, #f1f3f5 14px, #f1f3f5 28px); border-radius: 1.5rem; padding: 2rem; display: flex; align-items: center; justify-content: center; }
        .kiosk-card-stage .kiosk-card { width: 100%; }
        .kiosk-icon { width: 120px; height: 120px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 4rem; }
        .kiosk-timer { height: 4px; background: #eef0f3; border-radius: 99px; overflow: hidden; }
        .kiosk-timer div { height: 100%; background: var(--brand); width: 100%; }
        .kiosk-input { position: absolute; left: -9999px; opacity: 0; }
        @media (max-width: 767px) { .kiosk-steps { grid-template-columns: 1fr; } .kiosk-screen { padding: 2rem 1.25rem; } }
    </style>
@endpush

@section('content')
    @php($company = $page->branch?->company)
    <div class="kiosk">
        <div class="kiosk-top">
            <div class="kiosk-brand">
                @if ($company?->logoUrl()) <img src="{{ $company->logoUrl() }}" alt=""> @endif
                <div class="min-w-0">
                    <div class="fw-bold text-truncate">{{ $page->name }}</div>
                    <div class="small text-muted">Kiosk Cetak ID Card · {{ $scanner->name }}</div>
                </div>
            </div>
            <form method="POST" action="{{ route('scan.logout', request()->route('token')) }}" onsubmit="return confirm('Tutup kiosk ini? Untuk membuka lagi perlu PIN.');">
                @csrf
                <button type="submit" class="btn btn-sm btn-light text-muted"><i class="ri-logout-box-r-line"></i> Tutup kiosk</button>
            </form>
        </div>

        <div class="kiosk-panel position-relative">
            <form id="scan-form" autocomplete="off"><input type="text" id="scan-input" class="kiosk-input" maxlength="64" aria-label="Kode QR e-ticket" autofocus></form>

            {{-- 1. Menunggu peserta --}}
            <section class="kiosk-screen is-active flex-column align-items-center justify-content-center text-center" data-screen="idle">
                <div class="kiosk-qr-pulse mb-4"><i class="ri-qr-scan-2-line"></i></div>
                <div class="kiosk-hello">Halo, selamat datang!</div>
                <p class="kiosk-sub mt-2 mb-0">Yuk tukarkan e-ticket kamu jadi ID card di sini. Cepat kok, cuma beberapa detik.</p>
                <div class="kiosk-steps">
                    <div class="kiosk-step"><i class="ri-smartphone-line"></i><b>Buka e-ticket</b><span>dari email atau WhatsApp kamu</span></div>
                    <div class="kiosk-step"><i class="ri-qr-code-line"></i><b>Dekatkan QR-nya</b><span>ke scanner di depan kamu</span></div>
                    <div class="kiosk-step"><i class="ri-id-card-line"></i><b>Ambil ID card</b><span>di printer, lalu pakai ya</span></div>
                </div>
            </section>

            {{-- 2. Sedang mengecek --}}
            <section class="kiosk-screen flex-column align-items-center justify-content-center text-center" data-screen="loading">
                <div class="spinner-border mb-4" style="width: 4rem; height: 4rem; color: var(--brand);" role="status"></div>
                <div class="kiosk-hello" style="font-size: 2rem;">Sebentar ya…</div>
                <p class="kiosk-sub mt-2 mb-0" data-loading-text>Kami sedang mengecek e-ticket kamu.</p>
            </section>

            {{-- 3. Konfirmasi pemilik --}}
            <section class="kiosk-screen align-items-center" data-screen="confirm">
                <div class="row g-4 g-lg-5 align-items-center w-100 m-0">
                    <div class="col-md-5">
                        <div class="kiosk-card-stage"><div class="kiosk-card" data-preview></div></div>
                    </div>
                    <div class="col-md-7">
                        <div class="text-muted fs-5 mb-1">Halo,</div>
                        <div class="kiosk-hello" data-name></div>
                        <div class="fs-5 mt-2"><span class="badge rounded-pill bg-brand-soft text-brand px-3 py-2" data-category></span></div>
                        <p class="kiosk-sub mt-4">Apakah ini ID card kamu? Cek dulu namanya ya. Setelah dicetak, e-ticket ini tidak bisa dipakai cetak lagi.</p>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <button type="button" class="btn btn-brand kiosk-btn" data-action="print"><i class="ri-printer-line me-2"></i>Ya, cetak ID card saya</button>
                            <button type="button" class="btn btn-light kiosk-btn" data-action="cancel">Bukan saya</button>
                        </div>
                        <p class="small text-muted mt-4 mb-1">Namanya salah? Jangan cetak dulu, langsung hubungi petugas ya.</p>
                        <div class="kiosk-timer"><div data-timer></div></div>
                    </div>
                </div>
            </section>

            {{-- 4. Hasil (berhasil / perlu bantuan) --}}
            <section class="kiosk-screen flex-column align-items-center justify-content-center text-center" data-screen="result">
                <span class="kiosk-icon mb-4" data-result-icon></span>
                <div class="kiosk-hello" style="font-size: clamp(1.8rem, 3.4vw, 2.6rem);" data-result-title></div>
                <p class="kiosk-sub mt-3 mb-0 mx-auto" style="max-width: 640px;" data-result-text></p>
                <button type="button" class="btn btn-light kiosk-btn mt-4" data-action="cancel">Kembali ke awal</button>
            </section>
        </div>
        <p class="text-center small text-muted mt-3 mb-0"><i class="ri-shield-check-line me-1"></i>Satu e-ticket hanya bisa ditukar satu kali. Butuh bantuan? Hubungi petugas di dekat kiosk.</p>
    </div>

    <iframe id="print-frame" title="Cetak ID card" style="position: absolute; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

    @push('scripts')
        <script>
            (function () {
                var input = document.getElementById('scan-input');
                var screens = document.querySelectorAll('[data-screen]');
                var pendingCode = null, busy = false, timers = [];
                var results = {
                    ok: ['ri-checkbox-circle-fill', '#198754', 'Yeay, ID card kamu sedang dicetak!'],
                    already: ['ri-information-fill', '#0d6efd', 'ID card-nya sudah pernah dicetak'],
                    wrong_card: ['ri-id-card-fill', '#0d6efd', 'Kamu sudah punya ID card'],
                    disabled: ['ri-time-fill', '#fd7e14', 'Cetak ID card belum dibuka'],
                    not_found: ['ri-error-warning-fill', '#dc3545', 'Hmm, e-ticket-nya belum terbaca'],
                    error: ['ri-wifi-off-line', '#dc3545', 'Koneksinya sedang terputus']
                };

                function clearTimers() { timers.forEach(clearTimeout); timers = []; }
                function later(fn, ms) { timers.push(setTimeout(fn, ms)); }

                function show(name) {
                    screens.forEach(function (s) { s.classList.toggle('is-active', s.dataset.screen === name); });
                    input.focus();
                }

                function reset() { clearTimers(); pendingCode = null; busy = false; input.value = ''; show('idle'); }

                function result(data) {
                    var r = results[data.status] || results.error;
                    var icon = document.querySelector('[data-result-icon]');
                    icon.innerHTML = '<i class="' + r[0] + '"></i>';
                    icon.style.color = r[1];
                    icon.style.background = 'color-mix(in srgb, ' + r[1] + ' 12%, #fff)';
                    document.querySelector('[data-result-title]').textContent = r[2];
                    document.querySelector('[data-result-text]').textContent = data.message;
                    show('result');
                    later(reset, data.status === 'ok' ? 12000 : 9000);
                }

                function print(html) {
                    var frame = document.getElementById('print-frame');
                    frame.onload = function () {
                        var doc = frame.contentDocument;
                        (doc.fonts ? doc.fonts.ready : Promise.resolve()).then(function () {
                            setTimeout(function () { frame.contentWindow.focus(); frame.contentWindow.print(); }, 200);
                        });
                    };
                    frame.srcdoc = html;
                }

                function send(code, confirmed) {
                    if (busy) return;
                    busy = true;
                    clearTimers();
                    document.querySelector('[data-loading-text]').textContent = confirmed ? 'ID card kamu sedang disiapkan.' : 'Kami sedang mengecek e-ticket kamu.';
                    show('loading');
                    fetch({{ \Illuminate\Support\Js::from(route('scan.redeem', request()->route('token'))) }}, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ code: code, confirm: confirmed })
                    }).then(function (response) {
                        if (response.status === 401 || response.status === 419) { location.reload(); return null; }
                        return response.json();
                    }).then(function (data) {
                        busy = false;
                        if (!data) return;
                        if (data.status === 'ready') {
                            pendingCode = code;
                            document.querySelector('[data-preview]').innerHTML = data.preview;
                            document.querySelector('[data-name]').textContent = data.ticket.name;
                            document.querySelector('[data-category]').textContent = data.ticket.category;
                            show('confirm');
                            // Tidak ada respons 30 detik = kembali ke awal, supaya peserta berikutnya tidak mencetak kartu orang lain.
                            var bar = document.querySelector('[data-timer]');
                            bar.style.transition = 'none'; bar.style.width = '100%';
                            requestAnimationFrame(function () { requestAnimationFrame(function () { bar.style.transition = 'width 30s linear'; bar.style.width = '0%'; }); });
                            later(reset, 30000);
                            return;
                        }
                        if (data.status === 'ok' && data.print) print(data.print);
                        pendingCode = null;
                        result(data);
                    }).catch(function () {
                        busy = false;
                        result({ status: 'error', message: 'Coba scan sekali lagi ya. Kalau masih gagal, panggil petugas.' });
                    }).finally(function () { input.value = ''; input.focus(); });
                }

                document.getElementById('scan-form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    var code = input.value.trim();
                    input.value = '';
                    // Scan baru saat layar konfirmasi = peserta lain; mulai ulang dari awal.
                    if (code) send(code, false);
                });
                document.querySelectorAll('[data-action=print]').forEach(function (b) { b.addEventListener('click', function () { if (pendingCode) send(pendingCode, true); }); });
                document.querySelectorAll('[data-action=cancel]').forEach(function (b) { b.addEventListener('click', reset); });
                // Scanner USB/Bluetooth "mengetik" ke kotak input; fokus selalu dikembalikan.
                document.addEventListener('click', function (event) { if (!event.target.closest('form[method=POST]')) input.focus(); });
                setInterval(function () { if (document.activeElement !== input) input.focus(); }, 1500);
            })();
        </script>
    @endpush
@endsection
