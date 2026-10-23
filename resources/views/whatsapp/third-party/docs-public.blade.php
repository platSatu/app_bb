<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dokumentasi API WhatsApp · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="stylesheet" href="{{ asset('be') }}/assets/css/bootstrap.min.css">
    @include('whatsapp.third-party._docs-style')
    <style> body { background: #f5f7fb; } </style>
</head>
<body>
    <nav class="bg-white border-bottom mb-4">
        <div class="container py-3 d-flex align-items-center gap-2" style="max-width: 980px;">
            <img src="{{ asset('be') }}/assets/images/favicon.png" width="28" height="28" alt="">
            <span class="fw-semibold">{{ config('app.name') }}</span>
            <span class="text-muted">· Dokumentasi API WhatsApp</span>
        </div>
    </nav>
    <main class="container pb-5" style="max-width: 980px;">
        <div class="bg-white rounded-3 border p-3 p-md-4 api-docs">{!! $html !!}</div>
    </main>
</body>
</html>
