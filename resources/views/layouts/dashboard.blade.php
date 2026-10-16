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
