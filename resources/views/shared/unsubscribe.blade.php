<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Berhenti Menerima Email | {{ config('app.name') }}</title>
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    {{-- Link berhenti dari email Agent Prospek. Dibuka dulu (GET), baru berhenti setelah tombol ditekan (POST),
         supaya pemindai link di kotak masuk tidak memberhentikan orang tanpa sengaja. --}}
    <div class="min-vh-100 d-flex align-items-center justify-content-center p-4">
        <div class="text-center bg-white rounded shadow-sm p-4" style="max-width: 440px">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px">
                <i class="ri-mail-{{ $done ? 'check' : 'close' }}-line {{ $done ? 'text-success' : 'text-secondary' }}" style="font-size:2.4rem"></i>
            </div>
            @if ($done)
                <h4 class="mb-2">Baik, sudah kami catat</h4>
                <p class="text-muted mb-0">Email promo tidak akan dikirim lagi ke <b>{{ $email }}</b>. Terima kasih sudah memberi tahu kami, dan mohon maaf kalau sempat mengganggu.</p>
            @else
                <h4 class="mb-2">Berhenti menerima email promo?</h4>
                <p class="text-muted">Kami tidak akan mengirim email promo lagi ke <b>{{ $email }}</b>.</p>
                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-100">Ya, berhenti</button>
                </form>
                <p class="small text-muted mt-3 mb-0">Kalau tidak jadi, cukup tutup halaman ini.</p>
            @endif
        </div>
    </div>
</body>
</html>
