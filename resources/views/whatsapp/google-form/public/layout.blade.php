{{-- Layout publik ringan: tiket QR pendaftar, aplikasi registrasi ulang, dan dokumentasi API. --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="stylesheet" href="{{ asset('be') }}/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('be') }}/assets/css/icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <style>
        :root { --brand: #e36666; }
        body { font-family: 'Outfit', system-ui, sans-serif; background: linear-gradient(180deg, color-mix(in srgb, var(--brand) 9%, #fff) 0, #fbf9f9 420px) no-repeat, #fbf9f9; min-height: 100vh; }
        .btn-brand { background: var(--brand); border-color: var(--brand); color: #fff; } .btn-brand:hover { filter: brightness(.92); color: #fff; }
        .text-brand { color: var(--brand) !important; }
        .icon-circle { width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
    </style>
    @stack('head')
</head>
<body>
    <main class="container py-4 py-md-5" style="max-width: @yield('width', '460px');">
        @yield('content')
        <p class="text-center small text-muted mt-4 mb-0">Didukung oleh {{ config('app.name') }}</p>
    </main>
    @stack('scripts')
</body>
</html>
