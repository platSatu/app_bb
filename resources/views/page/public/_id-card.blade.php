{{--
    ID card peserta. Semua ukuran memakai satuan relatif lebar kartu (cqw), jadi tampilannya sama persis
    di preview admin, layar kiosk, dan hasil cetak (ukuran KTP maupun A5).
    $card dari TicketCardService::cardData().
--}}
@php
    $s = $card['settings'];
    [$paperW, $paperH] = \App\Services\Ticket\TicketCardService::paper($s);
    $landscape = $s['orientation'] === 'landscape';
    // Nama panjang dikecilkan dulu sebelum terpaksa dipotong, karena nama adalah isi terpenting kartu.
    $nameLength = mb_strlen($card['holder']);
    $nameClass = $nameLength > 38 ? 'is-longest' : ($nameLength > 22 ? 'is-long' : '');
@endphp

@once
    <style>
        .idc-wrap { container-type: inline-size; width: 100%; }
        .idc { --u: calc(1cqw * var(--k, 1)); position: relative; aspect-ratio: var(--ratio); overflow: hidden; background: #fff; color: #1f1f2b; border-radius: calc(var(--u) * 4); display: flex; flex-direction: column; font-family: 'Outfit', system-ui, sans-serif; box-shadow: 0 1.5cqw 5cqw rgba(16,24,40,.16); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .idc.is-landscape { display: grid; grid-template-columns: 1fr 40%; grid-template-rows: auto 1fr auto; }
        .idc-head { position: relative; color: #fff; padding: calc(var(--u) * 10) calc(var(--u) * 7) calc(var(--u) * 6); background: radial-gradient(circle at 88% -10%, rgba(255,255,255,.24), transparent 55%), linear-gradient(135deg, var(--tc), color-mix(in srgb, var(--tc) 45%, #1d1b2e)); }
        .idc.is-landscape .idc-head { grid-column: 1 / -1; padding-top: calc(var(--u) * 8); }
        .idc-hole { display: none; position: absolute; top: calc(var(--u) * 3.4); left: 50%; transform: translateX(-50%); width: calc(var(--u) * 15); height: calc(var(--u) * 3.2); border-radius: 99px; background: rgba(255,255,255,.92); }
        .idc.has-hole .idc-hole { display: block; }
        .idc-brand { display: flex; align-items: center; gap: calc(var(--u) * 2); font-size: calc(var(--u) * 3.4); font-weight: 500; opacity: .95; min-width: 0; }
        .idc-brand img { height: calc(var(--u) * 6.4); max-width: calc(var(--u) * 22); object-fit: contain; background: #fff; border-radius: calc(var(--u) * 1.2); padding: calc(var(--u) * .6); }
        .idc-brand span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .idc-event { font-size: calc(var(--u) * 6.2); font-weight: 700; line-height: 1.1; margin-top: calc(var(--u) * 3); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .idc-date { font-size: calc(var(--u) * 3.4); opacity: .85; margin-top: calc(var(--u) * 1); }
        .idc-info { display: flex; flex-direction: column; align-items: center; text-align: center; padding: calc(var(--u) * 6) calc(var(--u) * 6) 0; min-width: 0; }
        .idc.is-landscape .idc-info { align-items: flex-start; text-align: left; justify-content: center; padding: calc(var(--u) * 4) calc(var(--u) * 4) calc(var(--u) * 2) calc(var(--u) * 7); }
        .idc-label { background: color-mix(in srgb, var(--tc) 12%, #fff); color: var(--tc); font-weight: 700; letter-spacing: .18em; font-size: calc(var(--u) * 3.4); padding: calc(var(--u) * 1.2) calc(var(--u) * 4); border-radius: 99px; }
        .idc-name { font-size: calc(var(--u) * 8.4); font-weight: 700; line-height: 1.08; margin-top: calc(var(--u) * 3.4); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
        .idc-name.is-long { font-size: calc(var(--u) * 6.6); }
        .idc-name.is-longest { font-size: calc(var(--u) * 5.4); -webkit-line-clamp: 3; }
        .idc-cat { font-size: calc(var(--u) * 4); color: var(--tc); font-weight: 600; margin-top: calc(var(--u) * 1.4); }
        .idc-qr { margin: auto auto 0; padding-top: calc(var(--u) * 4); width: calc(var(--u) * 44); }
        .idc.is-card:not(.is-landscape) .idc-qr { width: calc(var(--u) * 54); }
        .idc.is-landscape .idc-qr { grid-column: 2; grid-row: 2 / 4; margin: auto; padding: calc(var(--u) * 3) calc(var(--u) * 6) calc(var(--u) * 3) 0; width: calc(var(--u) * 50); }
        .idc-qr-box { aspect-ratio: 1; background: #fff; border-radius: calc(var(--u) * 2.4); box-shadow: 0 0 0 .25cqw #ececf1; display: flex; align-items: center; justify-content: center; padding: calc(var(--u) * 1.4); }
        .idc-qr-box img { width: 100%; height: 100%; image-rendering: pixelated; }
        .idc-qr-box i { font-size: calc(var(--u) * 20); color: #c8c8d0; }
        .idc-foot { text-align: center; font-size: calc(var(--u) * 3.1); color: #6b6b78; letter-spacing: .06em; padding: calc(var(--u) * 3) calc(var(--u) * 6) calc(var(--u) * 5); }
        .idc.is-landscape .idc-foot { text-align: left; padding: 0 calc(var(--u) * 4) calc(var(--u) * 5) calc(var(--u) * 7); }
        .idc-foot b { color: #1f1f2b; }
    </style>
@endonce

<div class="idc-wrap">
    <div class="idc is-{{ $s['size'] }} {{ $landscape ? 'is-landscape' : '' }} {{ $s['hole'] ? 'has-hole' : '' }}" data-idc
        style="--tc: {{ $card['color'] }}; --ratio: {{ $paperW }} / {{ $paperH }}; --k: {{ $landscape ? round($paperH / $paperW, 4) : 1 }};">
        <div class="idc-head">
            <span class="idc-hole"></span>
            <div class="idc-brand">
                @if ($card['logo']) <img src="{{ $card['logo'] }}" alt=""> @endif
                @if ($card['company']) <span>{{ $card['company'] }}</span> @endif
            </div>
            <div class="idc-event">{{ $card['eventName'] }}</div>
            @if ($card['eventDate']) <div class="idc-date">{{ $card['eventDate'] }}</div> @endif
        </div>
        <div class="idc-info">
            <span class="idc-label" data-idc-label>{{ $s['label'] }}</span>
            <div class="idc-name {{ $nameClass }}">{{ $card['holder'] }}</div>
            <div class="idc-cat" data-idc-cat @unless ($s['show_category']) hidden @endunless>{{ $card['category'] }}</div>
        </div>
        <div class="idc-qr">
            <div class="idc-qr-box">
                @if ($card['qr']) <img src="{{ $card['qr'] }}" alt="QR ID card"> @else <i class="ri-qr-code-line"></i> @endif
            </div>
        </div>
        <div class="idc-foot"><b>{{ $card['number'] }}</b> · Kode {{ $card['booking'] }}</div>
    </div>
</div>
