@php
    /*
     * Halaman bayar publik /pay/{token} (5 Nov 2026: desain ulang).
     * Ringan: tanpa Bootstrap/icon font (hanya CSS inline + 1 font), supaya cepat terbuka di popup HP.
     * Alur tidak berubah: langsung diteruskan ke Duitku (autoPay), kembali ke sini lewat returnUrl,
     * status lunas HANYA dari Duitku (callback / cek status), popup memberi kabar ke origin terdaftar saja.
     */
    $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $company = $invoice->company;
    $lateFee = $invoice->currentLateFee();
    $final = ! $invoice->isUnpaid();
    $total = $invoice->isPaid() ? $invoice->paid_amount : $invoice->totalDue();
    $returnBack = array_filter(['token' => $invoice->token, 'returned' => 1, 'popup' => $origin ? 1 : null, 'origin' => $origin]);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#0f172a">
    <title>Pembayaran {{ $invoice->number }} · {{ $company?->name ?? 'bizbos' }}</title>
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php
        // Sambungan ke server Duitku disiapkan sejak awal supaya halaman Duitku terbuka lebih cepat.
        $duitkuUrl = $autoPayUrl ?: $openPayment?->payment_url;
        $duitkuOrigin = $duitkuUrl ? parse_url($duitkuUrl, PHP_URL_SCHEME).'://'.parse_url($duitkuUrl, PHP_URL_HOST) : null;
    @endphp
    @if ($duitkuOrigin && str_starts_with($duitkuOrigin, 'https://'))
        <link rel="preconnect" href="{{ $duitkuOrigin }}">
    @endif
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef0f3; --card: #ffffff; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc;
            --accent: #e11d48; --accent-ink: #ffffff; --accent-soft: #fff1f3;
            --ok: #059669; --ok-soft: #ecfdf5; --warn: #b45309; --warn-soft: #fffbeb; --bad: #dc2626; --bad-soft: #fef2f2;
        }
        * { box-sizing: border-box; border-radius: 0; }
        html, body { margin: 0; }
        body {
            min-height: 100vh; min-height: 100dvh; background: var(--bg); color: var(--ink);
            display: flex; flex-direction: column;
            font: 15px/1.5 "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased; font-variant-numeric: tabular-nums;
        }
        .top { background: var(--ink); color: #fff; }
        .top-in, .wrap { max-width: 480px; margin: 0 auto; padding: 0 16px; }
        .top-in { display: flex; align-items: center; justify-content: space-between; gap: 12px; height: 60px; }
        .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .brand img { height: 30px; width: auto; max-width: 110px; object-fit: contain; background: #fff; padding: 3px; }
        .brand-mark { width: 30px; height: 30px; background: var(--accent); display: grid; place-items: center; font-weight: 800; }
        .brand b { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .secure { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #cbd5e1; white-space: nowrap; }
        /* Kartu pembayaran berada di tengah layar (vertikal & horizontal); kalau lebih tinggi dari layar, mengalir biasa. */
        .wrap { flex: 1 0 auto; width: 100%; display: flex; flex-direction: column; justify-content: center; padding-top: 24px; padding-bottom: 32px; }

        .card { background: var(--card); border: 1px solid var(--line); }
        .hero { position: relative; padding: 22px 20px 20px; border-left: 4px solid var(--accent); }
        .hero.ok { border-left-color: var(--ok); } .hero.muted { border-left-color: #94a3b8; } .hero.bad { border-left-color: var(--bad); }
        .eyebrow { font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        .amount { font-size: 34px; font-weight: 800; letter-spacing: -.02em; line-height: 1.15; margin: 6px 0 2px; }
        .inv { font-size: 13px; color: var(--muted); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px; }
        .chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; font-size: 12.5px; font-weight: 600; background: var(--soft); border: 1px solid var(--line); }
        .chip.timer { background: var(--ink); color: #fff; border-color: var(--ink); }
        .chip.timer.urgent { background: var(--accent); border-color: var(--accent); }
        .chip.warn { background: var(--warn-soft); color: var(--warn); border-color: #fde68a; }
        .chip.bad { background: var(--bad-soft); color: var(--bad); border-color: #fecaca; }

        .state { display: flex; gap: 14px; align-items: flex-start; }
        .state-ico { flex: 0 0 44px; height: 44px; display: grid; place-items: center; }
        .state-ico.ok { background: var(--ok-soft); color: var(--ok); }
        .state-ico.bad { background: var(--bad-soft); color: var(--bad); }
        .state-ico.muted { background: var(--soft); color: var(--muted); }
        .state h1 { font-size: 19px; line-height: 1.3; margin: 0 0 2px; font-weight: 800; }
        .state p { margin: 0; color: var(--muted); font-size: 13.5px; }

        .rows { border-top: 1px solid var(--line); }
        .row { display: flex; justify-content: space-between; gap: 16px; padding: 12px 20px; border-bottom: 1px solid var(--line); font-size: 14px; }
        .row span:first-child { color: var(--muted); }
        .row span:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
        .row.desc span:first-child { color: var(--ink); }
        .row.total { background: var(--soft); font-size: 15px; }
        .row.total span { color: var(--ink) !important; font-weight: 800 !important; }

        .actions { padding: 18px 20px 20px; }
        .btn {
            display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; min-height: 54px; padding: 14px 18px;
            border: 0; cursor: pointer; text-decoration: none; font: inherit; font-weight: 700; font-size: 16px;
            background: var(--accent); color: var(--accent-ink); transition: filter .15s, transform .05s;
        }
        .btn:hover { filter: brightness(.94); } .btn:active { transform: translateY(1px); }
        .btn[disabled] { opacity: .7; cursor: progress; }
        .btn.dark { background: var(--ink); }
        .btn.ghost { background: transparent; color: var(--ink); border: 1.5px solid var(--ink); min-height: 48px; font-size: 15px; }
        .btn + .btn, .btn + .link { margin-top: 10px; }
        .link { display: block; text-align: center; color: var(--accent); font-weight: 600; font-size: 14px; text-decoration: none; padding: 8px; }
        .link:hover { text-decoration: underline; }
        .methods { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 14px; }
        .methods span { font-size: 11.5px; font-weight: 700; letter-spacing: .03em; color: var(--muted); border: 1px solid var(--line); padding: 4px 8px; background: #fff; }
        .note { font-size: 12.5px; color: var(--muted); text-align: center; margin-top: 10px; }

        .alert { display: flex; gap: 10px; padding: 12px 14px; margin-bottom: 14px; font-size: 13.5px; border: 1px solid; }
        .alert.bad { background: var(--bad-soft); color: #991b1b; border-color: #fecaca; }
        .alert.warn { background: var(--warn-soft); color: #92400e; border-color: #fde68a; }
        .alert svg { flex: 0 0 18px; margin-top: 1px; }

        .foot { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; font-size: 12.5px; color: var(--muted); text-align: center; }
        .foot b { color: var(--ink); }

        /* Layar transisi ke Duitku: langsung tampil, tidak menunggu apa pun. */
        .handoff { position: fixed; inset: 0; z-index: 10; background: rgba(238, 240, 243, .96); display: none; align-items: center; justify-content: center; padding: 24px; }
        .handoff.on { display: flex; }
        .handoff-box { width: 100%; max-width: 360px; background: #fff; border: 1px solid var(--line); padding: 26px 22px; text-align: center; }
        .handoff-box b { display: block; font-size: 17px; margin-bottom: 4px; }
        .handoff-box p { margin: 0; color: var(--muted); font-size: 13.5px; }
        .bar { height: 4px; background: var(--line); overflow: hidden; margin: 18px 0 14px; }
        .bar i { display: block; height: 100%; width: 40%; background: var(--accent); animation: slide 1.1s ease-in-out infinite; }
        @keyframes slide { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
        @media (prefers-reduced-motion: reduce) { .bar i { animation: none; width: 100%; } }
        @media (max-width: 380px) { .amount { font-size: 29px; } .secure span { display: none; } }
        .hidden { display: none !important; }
    </style>
</head>
<body>
<header class="top">
    <div class="top-in">
        <div class="brand">
            @if ($company?->logoUrl())
                <img src="{{ $company->logoUrl() }}" alt="">
            @else
                <span class="brand-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($company?->name ?? 'B', 0, 1)) }}</span>
            @endif
            <b>{{ $company?->name ?? 'bizbos' }}</b>
        </div>
        <span class="secure">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            <span>Pembayaran aman</span>
        </span>
    </div>
</header>

<main class="wrap">
    @if (session('error'))
        <div class="alert bad" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if ($timeUp)
        <div class="alert warn" role="status">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <div>
                <b>Batas waktu pembayaran sudah habis.</b>
                {{ $invoice->return_url ? 'Silakan buat pembayaran baru di '.parse_url($invoice->return_url, PHP_URL_HOST).'.' : 'Silakan minta tagihan baru ke penagih.' }}
                Kalau Anda sudah membayar sebelum batas waktu, status berubah otomatis setelah konfirmasi bank masuk.
            </div>
        </div>
    @elseif ($returned && $invoice->isUnpaid())
        <div class="alert warn" role="status" aria-live="polite" data-wait>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <div>Pembayaran belum kami terima. Kalau Anda sudah membayar, halaman ini akan berubah otomatis dalam beberapa saat.</div>
        </div>
    @endif

    <section class="card">
        @switch($invoice->status)
            @case('paid')
                <div class="hero ok">
                    <div class="state">
                        <div class="state-ico ok" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
                        <div>
                            <h1>Pembayaran berhasil</h1>
                            <p>Terima kasih! Dibayar {{ $invoice->paid_at?->translatedFormat('d F Y, H:i') }}</p>
                        </div>
                    </div>
                    <div class="amount" style="margin-top:14px">{{ $rp($total) }}</div>
                    <div class="inv">{{ $invoice->number }}</div>
                </div>
                @break
            @case('cancelled')
                <div class="hero bad">
                    <div class="state">
                        <div class="state-ico bad" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M6 6l12 12M18 6L6 18"/></svg></div>
                        <div>
                            <h1>Tagihan dibatalkan</h1>
                            <p>Tidak perlu dibayar. Hubungi penagih kalau ada pertanyaan.</p>
                        </div>
                    </div>
                </div>
                @break
            @case('expired')
                <div class="hero muted">
                    <div class="state">
                        <div class="state-ico muted" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5M9 2h6"/></svg></div>
                        <div>
                            @if (! $invoice->expires_at && $invoice->due_date)
                                <h1>Lewat jatuh tempo</h1>
                                <p>Jatuh tempo {{ $invoice->due_date->translatedFormat('d F Y') }}, link pembayaran sudah ditutup. Silakan hubungi penagih.</p>
                            @else
                                <h1>Batas waktu pembayaran habis</h1>
                                <p>Tidak ada dana yang terpotong. Silakan minta tagihan baru ke penagih.</p>
                            @endif
                        </div>
                    </div>
                </div>
                @break
            @default
                <div class="hero">
                    <div class="eyebrow">Total pembayaran</div>
                    <div class="amount">{{ $rp($total) }}</div>
                    <div class="inv">{{ $invoice->number }}</div>
                    <div class="chips">
                        @if ($deadline)
                            <span class="chip timer" data-deadline="{{ $deadline->getTimestamp() }}" aria-live="off">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5"/></svg>
                                <span>Bayar dalam <b data-clock>--:--</b></span>
                            </span>
                        @endif
                        @if ($invoice->isOverdue())
                            <span class="chip bad">Lewat jatuh tempo</span>
                        @elseif ($invoice->due_date)
                            <span class="chip">Jatuh tempo {{ $invoice->due_date->translatedFormat('d M Y') }}</span>
                        @endif
                    </div>
                    @if ($invoice->due_date && ! $invoice->expires_at && ! $invoice->chargesLateFee() && ! $invoice->isOverdue())
                        <div class="note" style="text-align:left;margin-top:10px">Lewat tanggal jatuh tempo, link pembayaran ditutup.</div>
                    @endif
                </div>
        @endswitch

        <div class="rows">
            <div class="row"><span>Ditagihkan kepada</span><span>{{ $invoice->customer_name }}</span></div>
            <div class="row desc"><span>{{ $invoice->description }}</span><span>{{ $rp($invoice->amount) }}</span></div>
            @if ($lateFee > 0)
                <div class="row"><span>Denda keterlambatan</span><span>{{ $rp($lateFee) }}</span></div>
            @endif
            <div class="row total"><span>Total</span><span>{{ $rp($total) }}</span></div>
        </div>

        <div class="actions">
            @if ($invoice->isUnpaid() && $timeUp)
                {{-- Tagihan ini tidak bisa dibayar lagi (batas waktu dari penagih): arahkan ke website penagih untuk membuat yang baru. --}}
                @if ($invoice->return_url)
                    <a href="{{ $invoice->return_url }}" class="btn" data-restart>Buat pembayaran baru</a>
                @endif
                <a href="{{ route('invoice.pay', $returnBack) }}" class="btn ghost" data-check>Cek status pembayaran</a>
                <div class="note hidden" data-check-note aria-live="polite"></div>
            @elseif ($invoice->isUnpaid())
                @if ($openPayment && $returned)
                    {{-- Order Duitku masih berlaku: lanjutkan order yang sama (nomor VA / QR yang sama), tidak membuat tagihan baru. --}}
                    <a href="{{ $openPayment->payment_url }}" class="btn" rel="noopener" data-handoff>Lanjutkan pembayaran</a>
                @endif
                <form method="POST" action="{{ route('invoice.pay.checkout', array_filter(['token' => $invoice->token, 'origin' => $origin])) }}" id="pay-form" @class(['hidden' => $openPayment && $returned])>
                    @csrf
                    <button type="submit" class="btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                        <span>Bayar Sekarang</span>
                    </button>
                </form>
                @if ($openPayment || $returned)
                    <a href="{{ route('invoice.pay', $returnBack) }}" class="link">Sudah bayar? Cek status pembayaran</a>
                @endif
                <div class="methods" aria-label="Metode pembayaran">
                    <span>VIRTUAL ACCOUNT</span><span>QRIS</span><span>E-WALLET</span><span>RETAIL</span>
                </div>
                @if ($invoice->chargesLateFee())
                    <div class="note">Denda setelah jatuh tempo: {{ $invoice->category->lateFeeSummary() }}.</div>
                @endif
            @elseif ($invoice->isPaid())
                {{-- Dibuka sebagai popup: tutup jendela ini (dimunculkan lewat JS). Selain itu: kembali ke website penagih. --}}
                <button type="button" class="btn dark hidden" data-close>Selesai, tutup jendela ini</button>
                <div class="note hidden" data-close-note>Jendela ini akan tertutup otomatis.</div>
                @if ($invoice->return_url)
                    <a href="{{ $invoice->return_url }}" @class(['btn', 'ghost', 'hidden' => $origin]) data-return>Kembali ke {{ parse_url($invoice->return_url, PHP_URL_HOST) }}</a>
                    <div class="note hidden" data-return-note>Anda akan diarahkan kembali otomatis dalam beberapa detik…</div>
                @endif
            @endif
        </div>
    </section>

    <p class="foot">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/></svg>
        <span>Diproses aman oleh <b>bizbos</b> &amp; <b>Duitku</b>. Status lunas hanya dari konfirmasi bank/penyedia.</span>
    </p>
</main>

<div class="handoff" id="handoff" role="status" aria-live="polite">
    <div class="handoff-box">
        <b>Menyiapkan pembayaran…</b>
        <p>Anda akan diarahkan ke halaman pembayaran Duitku.</p>
        <div class="bar"><i></i></div>
        <p style="font-size:12px">Jangan tutup jendela ini.</p>
    </div>
</div>

<script>
    (function () {
        var handoff = document.getElementById('handoff');
        var showHandoff = function () { if (handoff) handoff.classList.add('on'); };

        // Klik "Bayar Sekarang" / "Lanjutkan pembayaran": tampilkan layar transisi & cegah klik dobel.
        var form = document.getElementById('pay-form');
        if (form) form.addEventListener('submit', function () {
            var b = form.querySelector('button'); if (b) b.disabled = true; showHandoff();
        });
        document.querySelectorAll('[data-handoff]').forEach(function (a) { a.addEventListener('click', showHandoff); });

        // Kembali dari Duitku (bfcache): sembunyikan layar transisi supaya halaman bisa dipakai lagi.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted && handoff) { handoff.classList.remove('on'); if (form) { var b = form.querySelector('button'); if (b) b.disabled = false; } }
        });

        // Timer batas bayar. Habis = muat ulang; server yang menentukan status (ditutup / masih menunggu bank).
        var chip = document.querySelector('[data-deadline]');
        if (chip) {
            var end = Number(chip.getAttribute('data-deadline')) * 1000, clock = chip.querySelector('[data-clock]');
            var tick = function () {
                var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
                var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
                clock.textContent = (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
                chip.classList.toggle('urgent', left <= 300);
                if (left === 0) { clearInterval(t); setTimeout(function () { location.reload(); }, 1500); }
            };
            var t = setInterval(tick, 1000); tick();
        }
    })();
</script>

@if ($origin || $final || $returned)
    <script>
        (function () {
            var origin = @json($origin), status = @json($invoice->status), final = @json($final);
            // Order Duitku bisa dibuat lewat API (tanpa origin di return URL): ingat origin popup di tab ini.
            var key = 'bizbos-pay-origin:' + @json($invoice->id);
            try { origin ? sessionStorage.setItem(key, origin) : (origin = sessionStorage.getItem(key)); } catch (e) {}
            var payload = { source: 'bizbos-pay', status: status, invoice_id: @json($invoice->id), external_id: @json($invoice->external_id), number: @json($invoice->number) };

            // Kabar ke website yang membuka popup; hanya ke origin terdaftar.
            // Catatan: setelah pindah ke Duitku, browser bisa memutus window.opener — jadi website
            // tetap wajib mengecek status ke servernya sendiri, dan popup tetap menutup sendiri.
            if (origin && final && window.opener) {
                try { window.opener.postMessage(payload, origin); } catch (e) {}
            }

            // Lunas & dibuka sebagai popup: tutup rapi, jangan buka website penagih di dalam popup.
            if (origin && status === 'paid') {
                var close = document.querySelector('[data-close]'), note = document.querySelector('[data-close-note]'), back = document.querySelector('[data-return]');
                if (close) close.classList.remove('hidden');
                if (note) note.classList.remove('hidden');
                if (back) back.classList.add('hidden');
                var tutup = function (manual) {
                    window.close();
                    setTimeout(function () {
                        if (window.closed || !manual) return;
                        // Browser menolak menutup jendela: pakai jalur biasa.
                        if (back) { location.href = back.href; } else if (note) { note.textContent = 'Silakan tutup jendela ini secara manual.'; }
                    }, 400);
                };
                if (close) close.addEventListener('click', function () { tutup(true); });
                setTimeout(function () { tutup(false); }, 2500);
            }

            // Baru selesai bayar (bukan popup): kembali otomatis ke website penagih, satu tab saja.
            if (!origin && status === 'paid' && @json($returned)) {
                var backLink = document.querySelector('[data-return]'), backNote = document.querySelector('[data-return-note]');
                if (backLink) {
                    if (backNote) backNote.classList.remove('hidden');
                    setTimeout(function () { location.replace(backLink.href); }, 2500);
                }
            }

            // Baru kembali dari Duitku tapi belum terkonfirmasi: cek berkala sampai 10 menit.
            // Server mengecek ke Duitku paling sering 1x per 15 detik per invoice.
            if (!final && @json($returned)) {
                var tries = 0, timer = setInterval(function () {
                    if (++tries > 100 || document.hidden) { if (tries > 100) clearInterval(timer); return; }
                    fetch({{ \Illuminate\Support\Js::from(route('invoice.pay.status', $invoice->token)) }}, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) { if (data && data.status && data.status !== 'unpaid') location.reload(); })
                        .catch(function () {});
                }, 6000);
            }
        })();
    </script>
@endif
@if ($timeUp)
    <script>
        (function () {
            var check = document.querySelector('[data-check]'), note = document.querySelector('[data-check-note]'), restart = document.querySelector('[data-restart]');
            var origin = @json($origin);
            try { origin = origin || sessionStorage.getItem('bizbos-pay-origin:' + @json($invoice->id)); } catch (e) {}

            // "Cek status": tanya server (server mengecek ke Duitku), tampilkan hasilnya di sini; berubah = muat ulang.
            if (check) check.addEventListener('click', function (e) {
                e.preventDefault();
                if (check.getAttribute('aria-busy')) return;
                check.setAttribute('aria-busy', 'true');
                check.textContent = 'Mengecek...';
                fetch({{ \Illuminate\Support\Js::from(route('invoice.pay.status', $invoice->token)) }}, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (data && data.status && data.status !== 'unpaid') { location.reload(); return; }
                        note.textContent = data ? 'Belum ada pembayaran yang masuk untuk tagihan ini (dicek ' + new Date().toLocaleTimeString('id-ID') + ').' : 'Belum bisa mengecek sekarang, coba lagi sebentar.';
                        note.classList.remove('hidden');
                    })
                    .catch(function () { note.textContent = 'Koneksi terputus, coba lagi.'; note.classList.remove('hidden'); })
                    .then(function () { check.removeAttribute('aria-busy'); check.textContent = 'Cek status pembayaran'; });
            });

            if (!restart) return;

            // Dibuka sebagai popup: jangan buka website penagih di dalam popup, cukup tutup.
            if (origin) {
                restart.textContent = 'Tutup jendela ini';
                restart.addEventListener('click', function (e) { e.preventDefault(); window.close(); });
                return;
            }

            // Tombol Kembali browser: jangan kembali ke halaman Duitku yang sudah kedaluwarsa, tapi ke website penagih.
            if (window.history && history.pushState) {
                history.pushState({ bizbos: 'expired' }, '', location.href);
                window.addEventListener('popstate', function () { location.replace(restart.href); });
            }
        })();
    </script>
@endif
@if ($autoPay)
    <script>
        // Satu popup: langsung ke halaman bayar Duitku. Sekali per tab, supaya tombol "kembali" tidak memantul.
        (function () {
            var id = @json($invoice->id), origin = @json($origin), url = @json($autoPayUrl);
            try {
                if (sessionStorage.getItem('bizbos-pay-auto:' + id)) return;
                sessionStorage.setItem('bizbos-pay-auto:' + id, '1');
                if (origin) sessionStorage.setItem('bizbos-pay-origin:' + id, origin);
            } catch (e) {}
            var form = document.getElementById('pay-form'), button = form && form.querySelector('button');
            if (button) button.disabled = true;
            document.getElementById('handoff').classList.add('on');
            if (url) { location.replace(url); } else if (form) { form.submit(); }
        })();
    </script>
@endif
</body>
</html>
