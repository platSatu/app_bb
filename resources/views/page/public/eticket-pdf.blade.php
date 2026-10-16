<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 0; }
        .ticket { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        .banner { height: 170px; background-color: #1f2937; background-size: cover; background-position: center; position: relative; }
        .event { position: absolute; left: 16px; bottom: 14px; background: rgba(255,255,255,.85); padding: 8px 12px; border-radius: 8px; }
        .event b { font-size: 15px; }
        .body { padding: 18px; }
        .badge { display: inline-block; background: #111827; color: #fff; padding: 3px 10px; border-radius: 6px; font-size: 12px; }
        .muted { color: #6b7280; font-size: 11px; }
        .big { font-size: 22px; font-weight: bold; }
        td { vertical-align: top; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="banner" @if ($background) style="background-image: url('{{ $background }}');" @endif>
            <div class="event">
                <b>{{ $page->name }}</b>
                @if ($page->start_date) <div class="muted">{{ $page->start_date->translatedFormat('l, d F Y') }}</div> @endif
            </div>
        </div>
        <div class="body">
            <table width="100%">
                <tr>
                    <td>
                        <span class="badge">{{ $ticket->category_name }}</span>
                        <p class="muted" style="margin: 12px 0 2px;">Nama</p>
                        <div style="font-weight: bold;">{{ $ticket->holder_name }}</div>
                        <p class="muted" style="margin: 12px 0 2px;">Kode Booking</p>
                        <div class="big">{{ $ticket->booking_code }}</div>
                        <p class="muted" style="margin: 12px 0 2px;">No. Tiket</p>
                        <div style="font-weight: bold;">{{ $ticket->number }}</div>
                    </td>
                    <td width="180" style="text-align: center;">
                        <img src="{{ $qr }}" width="170" height="170" alt="QR">
                    </td>
                </tr>
            </table>
            <p class="muted" style="margin-top: 14px;">Tunjukkan QR ini saat registrasi ulang. Satu QR hanya berlaku untuk satu orang.</p>
        </div>
    </div>
</body>
</html>
