{{-- Settings > Aktivitas & Pengunjung: dua tab dalam satu menu. --}}
<ul class="nav nav-tabs mb-3">
    @foreach (['visitors' => ['Pengunjung Website', 'ri-global-line', 'superadmin.activity.visitors'], 'users' => ['Aktivitas Pengguna', 'ri-user-follow-line', 'superadmin.activity.users']] as $key => [$label, $icon, $route])
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $icon }} me-1"></i>{{ $label }}
            </a>
        </li>
    @endforeach
</ul>
