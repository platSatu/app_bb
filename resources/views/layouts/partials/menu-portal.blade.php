{{-- Sidebar portal murid/orang tua & pengajar: menu sesuai peran akun. --}}
@php
    $roles = auth()->user()->portalRoles();
    $portalMenu = array_filter([
        ['portal.home', 'Beranda', 'ri-home-5-line', true],
        ['portal.schedule', 'Jadwal Les', 'ri-calendar-event-line', $roles['student']],
        ['portal.attendance', 'Riwayat Kehadiran', 'ri-checkbox-circle-line', $roles['student']],
        ['portal.teaching', 'Jadwal Mengajar', 'ri-presentation-line', $roles['teacher']],
        ['portal.absen', 'Absen Murid', 'ri-list-check-3', $roles['teacher']],
        ['portal.profile', 'Profil', 'ri-user-3-line', true],
    ], fn ($item) => $item[3]);
@endphp

<li class="pe-menu-title">Portal</li>
@foreach ($portalMenu as [$route, $label, $icon])
    <li class="pe-slide">
        <a href="{{ route($route) }}" class="pe-nav-link {{ request()->routeIs($route.'*') ? 'active' : '' }}">
            <i class="{{ $icon }} pe-nav-icon"></i>
            <span class="pe-nav-content">{{ $label }}</span>
        </a>
    </li>
@endforeach
