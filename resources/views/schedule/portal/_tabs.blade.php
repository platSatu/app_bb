<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap">
    <li class="nav-item">
        <a href="{{ route('schedules.portal.requests') }}" class="nav-link {{ request()->routeIs('schedules.portal.requests') ? 'active' : '' }}">
            <i class="ri-user-received-2-line me-1"></i>Pendaftar
            @if ($pending ?? 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $pending }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('schedules.portal.links') }}" class="nav-link {{ request()->routeIs('schedules.portal.links') ? 'active' : '' }}">
            <i class="ri-links-line me-1"></i>Link Pendaftaran
        </a>
    </li>
</ul>
