{{--
    E-ticket landscape (tiket utama + sobekan berisi QR). Ukurannya mengikuti lebar layar
    lewat satuan cqw, jadi di HP tetap landscape, hanya mengecil. Dipakai halaman e-ticket & preview desain.
--}}
@php
    $color = $color ?? '#e36666';
    $mainBg = $background
        ? "background-image: linear-gradient(90deg, rgba(17,17,27,.78), rgba(17,17,27,.25)), url('".e($background)."');"
        : '';
@endphp

@once
    <style>
        .eticket-wrap { container-type: inline-size; max-width: 880px; margin: 0 auto; }
        .eticket { display: flex; aspect-ratio: 3 / 1.1; border-radius: 2cqw; overflow: hidden; background: #fff; box-shadow: 0 1.5cqw 4cqw rgba(16,24,40,.14); position: relative; }
        .et-main { flex: 0 0 74%; position: relative; color: #fff; background: linear-gradient(135deg, var(--tc), color-mix(in srgb, var(--tc) 45%, #1d1b2e)) center / cover no-repeat; }
        .et-content { position: absolute; inset: 0; padding: 3.2cqw 3.6cqw; display: flex; flex-direction: column; }
        .et-top { display: flex; align-items: center; gap: 1.2cqw; }
        .et-badge { background: #fff; color: var(--tc); font-weight: 700; font-size: 1.6cqw; padding: .45cqw 1.5cqw; border-radius: 99px; }
        .et-kicker { font-size: 1.25cqw; letter-spacing: .35em; opacity: .85; }
        .et-title { margin-top: auto; font-size: 4.4cqw; font-weight: 700; line-height: 1.05; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .et-meta { font-size: 1.6cqw; opacity: .92; margin-top: .9cqw; }
        .et-info { display: flex; gap: 3.4cqw; margin-top: 2cqw; padding-top: 1.6cqw; border-top: .12cqw solid rgba(255,255,255,.35); }
        .et-info small { display: block; font-size: 1.1cqw; letter-spacing: .14em; text-transform: uppercase; opacity: .75; }
        .et-info b { font-size: 2cqw; font-weight: 600; white-space: nowrap; }
        .et-stub { flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .9cqw; border-left: .35cqw dashed color-mix(in srgb, var(--tc) 40%, #fff); color: #1f1f2b; }
        .et-stub::before, .et-stub::after { content: ''; position: absolute; left: -1.75cqw; width: 3.2cqw; height: 3.2cqw; border-radius: 50%; background: var(--hole); }
        .et-stub::before { top: -1.6cqw; }
        .et-stub::after { bottom: -1.6cqw; }
        .et-admit { font-size: 1.15cqw; letter-spacing: .32em; font-weight: 700; color: var(--tc); }
        .et-qr { width: 14.5cqw; height: 14.5cqw; display: flex; align-items: center; justify-content: center; background: #f4f4f6; border-radius: 1cqw; }
        .et-qr img { width: 100%; height: 100%; }
        .et-code { font-size: 1.9cqw; font-weight: 700; letter-spacing: .12em; }
        .et-seq { font-size: 1.15cqw; color: #8a8a96; }
        .et-used { position: absolute; top: 50%; left: 37%; transform: translate(-50%, -50%) rotate(-10deg); border: .4cqw solid #fff; color: #fff; padding: .6cqw 2cqw; border-radius: 1cqw; font-size: 2.6cqw; font-weight: 700; letter-spacing: .2em; background: rgba(220,53,69,.75); }
    </style>
@endonce

<div class="eticket-wrap">
    <div class="eticket" style="--tc: {{ $color }}; --hole: {{ $hole ?? '#fbf9f9' }};">
        <div class="et-main" data-eticket-bg style="{{ $mainBg }}">
            <div class="et-content">
                <div class="et-top">
                    <span class="et-badge">{{ $category }}</span>
                    <span class="et-kicker">E-TICKET</span>
                </div>
                <div class="et-title">{{ $eventName }}</div>
                <div class="et-meta">{{ collect([$eventDate, $place ?? null])->filter()->join(' · ') }}</div>
                <div class="et-info">
                    <div class="text-truncate"><small>Nama</small><b>{{ $holder }}</b></div>
                    <div><small>No. Tiket</small><b>{{ $number }}</b></div>
                </div>
            </div>
        </div>
        <div class="et-stub">
            <div class="et-admit">ADMIT ONE</div>
            <div class="et-qr">
                @if ($qr) <img src="{{ $qr }}" alt="QR e-ticket"> @else <i class="ri-qr-code-line" style="font-size:7cqw;color:#bbb"></i> @endif
            </div>
            <div class="et-code">{{ $booking }}</div>
            <div class="et-seq">Tiket {{ $sequence }}</div>
        </div>
        @if ($used ?? false) <div class="et-used">SUDAH DIPAKAI</div> @endif
    </div>
</div>
