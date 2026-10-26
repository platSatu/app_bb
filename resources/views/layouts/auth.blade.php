<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <title>@yield('title') | {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script type="module" src="{{ asset('be') }}/assets/js/layout-setup.js"></script>
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">
    <style>
        @media (min-width: 1200px) {
            .main-wrapper { max-height: none; }
        }
    </style>
</head>

<body>
    <div class="min-vh-100 d-flex align-items-center justify-content-center py-10 px-5 auth-bg">
        <div class="main-wrapper border bg-white rounded-4 d-flex flex-column flex-lg-row gap-xl-5 position-relative overflow-hidden w-100 shadow">
            <div class="decoration-section m-5 bg-dark-subtle rounded-3 me-0 mb-0 mb-lg-5 overflow-hidden">
                <img src="{{ asset('be') }}/images/@yield('image', 'login_new.jpg')" alt="{{ config('app.name') }}" class="w-100 h-100" style="object-fit: cover;">
            </div>
            <div class="login-section bg-white rounded-4 p-6 px-xl-12">
                <a href="{{ route('login') }}" class="d-flex justify-content-end align-items-center gap-2 logo-main mt-lg-2 mb-8">
                    <img height="33" width="33" class="logo-dark" alt="Logo {{ config('app.name') }}" src="{{ asset('be') }}/assets/images/favicon.png">
                    <h3 class="mb-0 lh-base fw-semibold">{{ config('app.name') }}</h3>
                </a>

                @if (session('status') && session('status') !== 'verification-link-sent')
                    <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                @endif
                @if (session('warning'))
                    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    @stack('modals')

    <script src="{{ asset('be') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    <script>
        document.querySelectorAll('.toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.target);
                var icon = button.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.classList.toggle('ri-eye-line', show);
                icon.classList.toggle('ri-eye-off-line', !show);
            });
        });
    </script>
</body>
</html>
