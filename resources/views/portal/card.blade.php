<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Kartu Absen {{ $student->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1f2937; background: #f3f4f6; }
        .card { width: 100%; max-width: 360px; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, .08); text-align: center; }
        .head { padding: 20px 24px; background: #e25c5a; color: #fff; }
        .head small { display: block; opacity: .8; font-size: 13px; letter-spacing: .04em; text-transform: uppercase; }
        .head strong { display: block; font-size: 18px; margin-top: 4px; }
        .body { padding: 24px; }
        .qr { width: 100%; max-width: 260px; aspect-ratio: 1; image-rendering: pixelated; }
        h1 { font-size: 22px; margin: 12px 0 4px; }
        p { margin: 0; color: #6b7280; font-size: 14px; line-height: 1.5; }
        .tip { margin-top: 16px; padding: 12px; border-radius: 12px; background: #fdeeee; color: #9b2c2c; font-size: 14px; }
        .actions { display: flex; gap: 12px; padding: 0 24px 24px; }
        .actions a, .actions button { flex: 1; padding: 12px; border-radius: 12px; border: 1px solid #f5c2c1; background: #fff; color: #c94442;
            font: inherit; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .actions a { background: #e25c5a; border-color: #e25c5a; color: #fff; }
        @media print { body { background: #fff; } .card { box-shadow: none; border: 1px solid #e5e7eb; } .actions, .tip { display: none; } }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <small>Kartu Absen</small>
            <strong>{{ $student->branch?->name }}</strong>
        </div>
        <div class="body">
            <img src="{{ $qr }}" alt="QR absen {{ $student->name }}" class="qr">
            <h1>{{ $student->name }}</h1>
            <p>Tunjukkan QR ini ke pengajar saat les dimulai, kehadiran langsung tercatat.</p>
            <div class="tip">Simpan gambarnya atau tambahkan halaman ini ke layar utama HP supaya mudah dibuka.</div>
        </div>
        <div class="actions">
            <a href="{{ $qr }}" download="kartu-absen-{{ \Illuminate\Support\Str::slug($student->name) }}.png">Simpan QR</a>
            <button type="button" onclick="window.print()">Cetak</button>
        </div>
    </div>
</body>
</html>
