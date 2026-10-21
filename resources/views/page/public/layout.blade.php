<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        // Semua variabel layout di satu blok (jangan campur bentuk sebaris dengan blok, Blade bisa salah baca).
        $seo = $page->seo();
        $gaIds = $page->analyticsIds();
        // Menu & tema dari landing "Beranda" (kalau page ini termasuk di menunya).
        $nav = ($open ?? true) ? \App\Support\LandingNavigation::for($page) : null;
        $company = $page->branch?->company;
        // Banner header: hanya di halaman utama page (bukan checkout/scan/pesanan).
        $banner = $page->banner && request()->routeIs('page.show') && ($open ?? true) ? $page->bannerUrl() : null;
    @endphp
    <title>@yield('title', $seo['title'])</title>
    <meta name="robots" content="{{ ($open ?? true) ? 'index,follow' : 'noindex' }}">
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $page->branch?->company?->name ?? config('app.name') }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    @if ($gaIds)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaIds[0] }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());
            @json($gaIds).forEach(function (id) { gtag('config', id); });
        </script>
    @endif
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet">
    {{-- Font sama dengan dashboard (Outfit), kecuali landing memilih font sendiri. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ $nav ? \App\Support\LandingNavigation::FONTS[$nav['font']] : 'Outfit' }}:wght@400;500;600;700&display=swap">
    @stack('head')
    <style>
        :root { --brand: {{ $nav['color'] ?? '#e36666' }}; }
        body { font-family: '{{ $nav['font'] ?? 'Outfit' }}', system-ui, sans-serif; background: linear-gradient(180deg, color-mix(in srgb, var(--brand) 9%, #fff) 0, #fbf9f9 480px) no-repeat, #fbf9f9; }
        .page-banner img { aspect-ratio: 16 / 9; max-height: 540px; object-fit: cover; }
        .product-thumb { aspect-ratio: 1 / 1; object-fit: cover; }
        .cart-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; }
        .btn-brand { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-brand:hover { filter: brightness(.92); color: #fff; }
        .text-brand { color: var(--brand) !important; }
        .bg-brand-soft { background: color-mix(in srgb, var(--brand) 8%, #fff); }
        .site-nav .nav-link.active { color: var(--brand); font-weight: 600; }
    </style>
</head>
<body>
    @if ($nav)
        <nav class="navbar navbar-expand-md bg-white border-bottom sticky-top site-nav">
            <div class="container" style="max-width: 1140px;">
                <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="{{ $nav['home']->url() }}">
                    @if ($nav['logo']) <img src="{{ $nav['logo'] }}" alt="" height="34" style="object-fit: contain;"> @endif
                    <span>{{ $company?->name ?? $nav['home']->name }}</span>
                </a>
                @if ($nav['items'])
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#site-menu" aria-label="Menu"
                        onclick="document.getElementById('site-menu').classList.toggle('show')"><span class="navbar-toggler-icon"></span></button>
                    <div class="collapse navbar-collapse" id="site-menu">
                        <ul class="navbar-nav ms-auto">
                            @foreach ($nav['items'] as $item)
                                <li class="nav-item"><a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </nav>
    @endif

    {{-- Landing: banner selebar layar; page lain: di dalam kontainer (di bawah). --}}
    @if ($banner && $__env->hasSection('bare'))
        <div class="page-banner"><img src="{{ $banner }}" alt="{{ $page->name }}" class="d-block w-100" fetchpriority="high"></div>
    @endif

    @hasSection('bare')
        @yield('content')
    @else
        <main class="container py-4 py-md-5" style="max-width: @yield('width', '720px');">
            @unless ($nav)
                @if ($company)
                    <div class="d-flex justify-content-center mb-4">
                        <span class="d-inline-flex align-items-center gap-2 bg-white rounded-pill shadow-sm px-3 py-2">
                            @if ($company->logoUrl())
                                <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" height="28" style="object-fit: contain;">
                            @endif
                            <span class="fw-semibold">{{ $company->name }}</span>
                        </span>
                    </div>
                @endif
            @endunless

            @if ($banner)
                <div class="page-banner rounded-4 overflow-hidden shadow-sm mb-4"><img src="{{ $banner }}" alt="{{ $page->name }}" class="d-block w-100" fetchpriority="high"></div>
            @endif

            @yield('content')
        </main>
    @endif
    @stack('scripts')
</body>
</html>
