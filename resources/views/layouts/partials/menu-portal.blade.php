{{-- Bagian sidebar per peran: muncul kalau akun terhubung sebagai pengajar, murid/orang tua, dan/atau punya tagihan. --}}
@php
    $roles = auth()->user()->portalRoles();
    $sections = array_filter([
        'Student' => $roles['student'] ? [['portal.schedule', 'Jadwal Les', 'ri-calendar-event-line'], ['portal.attendance', 'Riwayat Kehadiran', 'ri-checkbox-circle-line']] : null,
        'Pengajar' => $roles['teacher'] ? [['portal.teaching', 'Jadwal Mengajar', 'ri-presentation-line'], ['portal.absen', 'Absen Murid', 'ri-list-check-3']] : null,
        'Tagihan' => $roles['payer'] ? [['bills.index', 'Tagihan Saya', 'ri-bill-line']] : null,
    ]);
@endphp

@foreach ($sections as $title => $items)
    <li class="pe-menu-title">{{ $title }}</li>
    @foreach ($items as [$route, $label, $icon])
        <li class="pe-slide">
            <a href="{{ route($route) }}" class="pe-nav-link {{ request()->routeIs($route.'*') ? 'active' : '' }}">
                <i class="{{ $icon }} pe-nav-icon"></i>
                <span class="pe-nav-content">{{ $label }}</span>
            </a>
        </li>
    @endforeach
@endforeach
