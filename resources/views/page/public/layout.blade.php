<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php($seo = $page->seo())
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
    @if ($gaIds = $page->analyticsIds())
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
    @php
        // Menu & tema dari landing "Beranda" (kalau page ini termasuk di menunya).
        $nav = ($open ?? true) ? \App\Support\LandingNavigation::for($page) : null;
        $company = $page->branch?->company;
    @endphp
    @if ($nav)
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ \App\Support\LandingNavigation::FONTS[$nav['font']] }}:wght@400;600;700&display=swap">
    @endif
    @stack('head')
    <style>
        :root { --brand: {{ $nav['color'] ?? '#4f46e5' }}; }
        @if ($nav) body { font-family: '{{ $nav['font'] }}', system-ui, sans-serif; } @endif
        .product-thumb { aspect-ratio: 1 / 1; object-fit: cover; }
        .cart-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; }
        .btn-brand { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-brand:hover { filter: brightness(.92); color: #fff; }
        .text-brand { color: var(--brand) !important; }
        .bg-brand-soft { background: color-mix(in srgb, var(--brand) 8%, #fff); }
        .site-nav .nav-link.active { color: var(--brand); font-weight: 600; }
    </style>
</head>
<body class="bg-light">
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

    {{-- Banner header: hanya di halaman utama page (bukan checkout/scan/pesanan). --}}
    @if ($page->banner && request()->routeIs('page.show') && ($open ?? true))
        <div class="page-banner bg-white">
            <img src="{{ $page->bannerUrl() }}" alt="{{ $page->name }}" class="d-block w-100" style="aspect-ratio:16/9;max-height:540px;object-fit:cover" fetchpriority="high">
        </div>
    @endif

    @hasSection('bare')
        @yield('content')
    @else
        <main class="container py-4 py-md-5" style="max-width: @yield('width', '720px');">
            @unless ($nav)
                <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                    @if ($company?->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" height="44" style="object-fit: contain;">
                    @endif
                    @if ($company)
                        <span class="fw-semibold">{{ $company->name }}</span>
                    @endif
                </div>
            @endunless

            @yield('content')
        </main>
    @endif
    @stack('scripts')
</body>
</html>
