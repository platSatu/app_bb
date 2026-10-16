@php
    $icons = ['superadmin.users' => 'ri-user-line', 'superadmin.companies' => 'ri-building-line', 'superadmin.branches' => 'ri-git-branch-line', 'superadmin.referral' => 'ri-gift-line'];
@endphp

<ul class="nav nav-tabs mb-3">
    @foreach (\App\Support\Menu::tabs(auth()->user(), array_keys($icons)) as $key => $tab)
        <li class="nav-item">
            <a href="{{ route($tab['route']) }}" class="nav-link {{ $active === $key ? 'active' : '' }}"
                @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $icons[$key] }} me-1"></i>{{ $tab['label'] }}
            </a>
        </li>
    @endforeach
</ul>
