<!DOCTYPE html>
<html lang="id">
<head>
    @php
        $pageTitle = trim($__env->yieldContent('title')) ?: 'Beranda';
        $roles = Auth::user()->portalRoles();
        $nav = collect([
            ['portal.home', 'Beranda', 'Beranda', 'ri-home-5-line', true],
            ['portal.schedule', 'Jadwal Les', 'Jadwal', 'ri-calendar-event-line', $roles['student']],
            ['portal.attendance', 'Riwayat Kehadiran', 'Kehadiran', 'ri-checkbox-circle-line', $roles['student']],
            ['portal.teaching', 'Jadwal Mengajar', 'Mengajar', 'ri-presentation-line', $roles['teacher']],
            ['portal.absen', 'Absen Murid', 'Absen', 'ri-list-check-3', $roles['teacher']],
            ['portal.profile', 'Profil', 'Profil', 'ri-user-3-line', true],
        ])->filter(fn ($item) => $item[4]);
    @endphp

    <meta charset="utf-8" />
    <title>{{ $pageTitle }} | {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <link href="{{ asset('be') }}/assets/css/app.min.css" rel="stylesheet" type="text/css">
    <style>
        body { background: #f5f7fb; padding-bottom: 80px; }
        .portal-top { background: #fff; border-bottom: 1px solid rgba(0,0,0,.06); position: sticky; top: 0; z-index: 1020; }
        .portal-top .nav-link { color: #6c757d; border-radius: 999px; padding: .45rem .9rem; font-size: .9rem; }
        .portal-top .nav-link.active { background: var(--bs-primary); color: #fff; }
        .portal-bottom { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid rgba(0,0,0,.08); z-index: 1030; padding-bottom: env(safe-area-inset-bottom); }
        .portal-bottom a { flex: 1; text-align: center; color: #8a94a6; font-size: .7rem; padding: .5rem 0 .4rem; text-decoration: none; }
        .portal-bottom a i { display: block; font-size: 1.35rem; line-height: 1.2; }
        .portal-bottom a.active { color: var(--bs-primary); font-weight: 600; }
        .portal-card { border: 0; border-radius: 1rem; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
        .pill-tabs .btn { border-radius: 999px; }
        .day-chip { width: 52px; border-radius: .75rem; }
        @media (min-width: 992px) { body { padding-bottom: 0; } }
    </style>
    @stack('styles')
</head>

<body>
    <header class="portal-top">
        <div class="container-xl d-flex align-items-center gap-3 py-2">
            <a href="{{ route('portal.home') }}" class="d-flex align-items-center gap-2 text-body text-decoration-none me-lg-3">
                <img height="30" width="30" alt="Logo" src="{{ asset('be') }}/assets/images/favicon.png">
                <span class="fw-semibold fs-16">{{ config('app.name') }}</span>
            </a>
            <nav class="d-none d-lg-flex gap-1 flex-grow-1">
                @foreach ($nav as [$route, $label, $short, $icon])
                    <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route.'*') ? 'active' : '' }}"><i class="{{ $icon }} me-1"></i>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="dropdown ms-auto">
                <button class="btn p-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-none d-md-block text-end lh-sm">
                        <span class="d-block fs-14">{{ Auth::user()->name }}</span>
                        <span class="text-muted fs-12">{{ Auth::user()->email }}</span>
                    </span>
                    <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold" style="width:38px;height:38px">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                    <li><a class="dropdown-item" href="{{ route('portal.profile') }}"><i class="ri-user-3-line me-2"></i>Profil Saya</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="ri-logout-box-r-line me-2"></i>Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="container-xl py-4">
        <h4 class="mb-3 fw-semibold">{{ $pageTitle }}</h4>
        @include('layouts.partials.flash')
        @yield('content')
    </main>

    <nav class="portal-bottom d-flex d-lg-none">
        @foreach ($nav as [$route, $label, $short, $icon])
            <a href="{{ route($route) }}" class="{{ request()->routeIs($route.'*') ? 'active' : '' }}"><i class="{{ $icon }}"></i>{{ $short }}</a>
        @endforeach
    </nav>

    <script src="{{ asset('be') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('pageshow', function (event) { if (event.persisted) window.location.reload(); });
    </script>
    @stack('scripts')
</body>
</html>
