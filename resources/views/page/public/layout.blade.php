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
        // Banner header selalu ada di halaman utama page (bukan checkout/scan/pesanan). Tanpa gambar: warna brand yang kalem.
        $hero = request()->routeIs('page.show') && ($open ?? true);
        $banner = $hero && $page->banner ? $page->bannerUrl() : null;
        // Lebar header = lebar isi, supaya sejajar.
        $width = $__env->hasSection('bare') ? '1140px' : ($__env->yieldContent('width') ?: '720px');
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
        h1, h2, h3, h4, h5, h6 { font-family: inherit; }
        .page-hero { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center; min-height: clamp(140px, 24vw, 240px); padding: 1.5rem; border-radius: 1.25rem; box-shadow: 0 6px 20px rgba(16,24,40,.08); background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 9%, #fff), color-mix(in srgb, var(--brand) 22%, #fff)); color: color-mix(in srgb, var(--brand) 70%, #000); }
        .page-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .page-hero.has-image { color: #fff; }
        .page-hero.has-image::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.2), rgba(0,0,0,.45)); }
        .page-hero h1 { position: relative; z-index: 1; margin: 0; font-weight: 700; font-size: clamp(1.6rem, 5vw, 3.2rem); line-height: 1.15; }
        .page-hero.has-image h1 { text-shadow: 0 2px 12px rgba(0,0,0,.35); }
        .product-thumb { aspect-ratio: 1 / 1; object-fit: cover; }
        .cart-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; }
        /* Lewat variabel Bootstrap: :hover/:active/:disabled bawaan .btn tidak lagi menimpa warnanya jadi transparan. */
        .btn-brand {
            --bs-btn-color: #fff; --bs-btn-bg: var(--brand); --bs-btn-border-color: var(--brand);
            --bs-btn-hover-color: #fff; --bs-btn-hover-bg: color-mix(in srgb, var(--brand) 88%, #000); --bs-btn-hover-border-color: color-mix(in srgb, var(--brand) 88%, #000);
            --bs-btn-active-color: #fff; --bs-btn-active-bg: color-mix(in srgb, var(--brand) 80%, #000); --bs-btn-active-border-color: color-mix(in srgb, var(--brand) 80%, #000);
            --bs-btn-focus-shadow-rgb: 0, 0, 0;
            --bs-btn-disabled-color: #fff; --bs-btn-disabled-bg: color-mix(in srgb, var(--brand) 45%, #fff); --bs-btn-disabled-border-color: transparent; --bs-btn-disabled-opacity: 1;
        }
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

    @if ($hero)
        <div class="container pt-3 pt-md-4" style="max-width: {{ $width }};">
            <header class="page-hero {{ $banner ? 'has-image' : '' }}">
                @if ($banner) <img src="{{ $banner }}" alt="" fetchpriority="high"> @endif
                <h1>{{ $page->name }}</h1>
            </header>
        </div>
    @endif

    @hasSection('bare')
        @yield('content')
    @else
        <main class="container py-4 py-md-5" style="max-width: {{ $width }};">
            @yield('content')
        </main>
    @endif
    @stack('scripts')
</body>
</html>
