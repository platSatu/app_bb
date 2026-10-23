<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $pageTitle = trim($__env->yieldContent('title')) ?: 'Dashboard';
        $pageSection = trim($__env->yieldContent('page-section'));
    @endphp

    <meta charset="utf-8" />
    <title>{{ $pageTitle }} | {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script type="module" src="{{ asset('be') }}/assets/js/layout-setup.js"></script>
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="stylesheet" href="{{ asset('be') }}/assets/libs/simplebar/simplebar.min.css">
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">

    <style>
        [data-sidebar="icon"] .pe-app-sidebar .pe-main-menu > li.pe-slide:not(.pe-has-sub) > .pe-nav-link .pe-nav-content {
            display: none;
        }
    </style>
    @stack('styles')
</head>

<body>
<div id="layout-wrapper">
    @include('layouts.partials.header')
    <div class="header-wrapper"></div>

    @include('layouts.partials.menu')

    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <main class="app-wrapper">
        <div class="container-fluid">
            <div class="main-breadcrumb d-flex flex-wrap align-items-center my-4 position-relative gap-3">
                <h2 class="breadcrumb-title mb-0 flex-grow-1 fs-16">{{ $pageTitle }}</h2>
                <div class="flex-shrink-0">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-end mb-0">
                            <li><a href="{{ route('dashboard') }}"><i class="ri-home-4-line fs-16 me-2 lh-sm text-primary"></i></a></li>
                            @if ($pageSection && $pageSection !== $pageTitle)
                                <li class="breadcrumb-item">{{ $pageSection }}</li>
                            @endif
                            <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            @include('layouts.partials.flash')
            @yield('content')
        </div>
    </main>

    @include('layouts.partials.scroll')
    @include('layouts.partials.footer')
</div>

<script src="{{ asset('be') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('be') }}/assets/libs/simplebar/simplebar.min.js"></script>
<script src="{{ asset('be') }}/assets/js/scroll-top.init.js"></script>
<script src="{{ asset('be') }}/assets/js/app.js"></script>
<script>
    // Tombol salin/bagikan link referral (header & tab Referral Saya).
    document.querySelectorAll('[data-referral-share]').forEach(function (block) {
        var link = block.dataset.link;
        var copyButton = block.querySelector('[data-copy]');
        var shareButton = block.querySelector('[data-share]');

        if (copyButton) {
            copyButton.addEventListener('click', function (event) {
                event.stopPropagation();
                var done = function () {
                    var icon = copyButton.querySelector('i');
                    var original = icon.className;
                    icon.className = original.replace('ri-file-copy-line', 'ri-check-line text-success');
                    setTimeout(function () { icon.className = original; }, 1200);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(link).then(done);
                } else {
                    var temp = document.createElement('textarea');
                    temp.value = link;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    temp.remove();
                    done();
                }
            });
        }

        if (shareButton && navigator.share) {
            shareButton.classList.remove('d-none');
            shareButton.addEventListener('click', function (event) {
                event.stopPropagation();
                navigator.share({ url: link }).catch(function () {});
            });
        }
    });

    // Input nominal rupiah: <input data-rupiah>. Saat mengetik tampil 1.500.000, yang dikirim ke server tetap 1500000.
    (function () {
        var digits = function (v) { return String(v).replace(/\D/g, '').replace(/^0+(?=\d)/, ''); };
        var format = function (v) { return digits(v).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
        var paint = function (input) {
            // Kursor tetap di posisi angka yang sama walau titik bertambah/berkurang.
            var before = digits(input.value.slice(0, input.selectionStart || 0)).length;
            input.value = format(input.value);
            if (document.activeElement !== input) return;
            var pos = 0;
            for (var seen = 0; pos < input.value.length && seen < before; pos++) if (/\d/.test(input.value[pos])) seen++;
            input.setSelectionRange(pos, pos);
        };
        window.rupiahValue = function (input) { return Number(digits(input.value)) || 0; };

        document.querySelectorAll('input[data-rupiah]').forEach(function (input) {
            input.type = 'text';
            input.inputMode = 'numeric';
            input.autocomplete = 'off';
            input.value = format(input.value);
            input.addEventListener('input', function () { paint(input); });
        });
        // Sebelum terkirim titiknya dibuang; kalau batal (mis. konfirmasi ditolak) tampil berformat lagi.
        document.addEventListener('submit', function (event) {
            var inputs = event.target.querySelectorAll('input[data-rupiah]');
            inputs.forEach(function (input) { input.value = digits(input.value); });
            setTimeout(function () { if (event.defaultPrevented) inputs.forEach(function (input) { input.value = format(input.value); }); });
        }, true);
    })();

    // Halaman dari back/forward cache dimuat ulang supaya tetap melewati cek login.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@stack('scripts')
</body>
</html>
