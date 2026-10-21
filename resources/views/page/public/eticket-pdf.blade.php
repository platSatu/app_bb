<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    {{-- Versi PDF dari e-ticket landscape (dompdf: tanpa flex/grid, ukuran px tetap 880×322). --}}
    @php
        $color = $page->setting('ticket.color') ?: '#e36666';
        $meta = collect([$page->start_date?->translatedFormat('D, d M Y'), $page->branch?->name])->filter()->join(' · ');
    @endphp
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1f1f2b; }
        table { border-collapse: collapse; }
        .main { width: 650px; height: 322px; vertical-align: top; color: #fff; background-color: {{ $color }}; background-size: cover; background-position: center; }
        .shade { height: 270px; padding: 26px 30px; background-color: {{ $background ? 'rgba(17,17,27,0.55)' : 'transparent' }}; }
        .badge { display: inline-block; background: #fff; color: {{ $color }}; font-weight: bold; font-size: 12px; padding: 3px 12px; border-radius: 10px; }
        .kicker { font-size: 10px; letter-spacing: 3px; margin-left: 8px; }
        .title { font-size: 30px; font-weight: bold; line-height: 1.1; margin-top: 70px; }
        .meta { font-size: 12px; margin-top: 6px; }
        .info { margin-top: 18px; border-top: 1px solid rgba(255,255,255,0.4); padding-top: 12px; }
        .info td { padding-right: 34px; vertical-align: top; }
        .label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; }
        .value { font-size: 14px; font-weight: bold; }
        .stub { width: 230px; height: 322px; text-align: center; vertical-align: middle; border-left: 3px dashed {{ $color }}; background: #fff; }
        .admit { font-size: 9px; letter-spacing: 3px; font-weight: bold; color: {{ $color }}; }
        .code { font-size: 16px; font-weight: bold; letter-spacing: 2px; margin-top: 6px; }
        .seq { font-size: 9px; color: #8a8a96; margin-top: 2px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="main" @if ($background) style="background-image: url('{{ $background }}');" @endif>
                <div class="shade">
                    <span class="badge">{{ $ticket->category_name }}</span><span class="kicker">E-TICKET</span>
                    <div class="title">{{ $page->name }}</div>
                    @if ($meta) <div class="meta">{{ $meta }}</div> @endif
                    <table class="info">
                        <tr>
                            <td><div class="label">Nama</div><div class="value">{{ $ticket->holder_name }}</div></td>
                            <td><div class="label">No. Tiket</div><div class="value">{{ $ticket->number }}</div></td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="stub">
                <div class="admit">ADMIT ONE</div>
                <img src="{{ $qr }}" width="150" height="150" alt="QR" style="margin-top: 8px;">
                <div class="code">{{ $ticket->booking_code }}</div>
                <div class="seq">Tiket {{ $ticket->sequence }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
