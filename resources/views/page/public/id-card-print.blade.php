<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }}</title>
    @php([$paperW, $paperH] = $paper)
    <link rel="stylesheet" href="{{ asset('be') }}/assets/css/icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <style>
        @page { size: {{ $paperW }}mm {{ $paperH }}mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; }
        .sheet { width: {{ $paperW }}mm; height: {{ $paperH }}mm; overflow: hidden; }
        .sheet .idc { border-radius: 0; box-shadow: none; }
        @media screen {
            body { background: #eef0f4; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .sheet { box-shadow: 0 10px 30px rgba(16,24,40,.18); }
        }
    </style>
</head>
<body>
    <div class="sheet">
        @include('page.public._id-card', ['card' => $card])
    </div>
    @if ($autoPrint)
        <script>
            // Tunggu font & QR selesai dimuat supaya hasil cetak tidak pakai font cadangan.
            window.addEventListener('load', function () {
                (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 150); });
            });
        </script>
    @endif
</body>
</html>
