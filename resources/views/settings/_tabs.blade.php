@php
    $tabs = [
        'company' => ['label' => 'Perusahaan', 'icon' => 'ri-building-line', 'route' => 'settings.company.show'],
        'branch' => ['label' => 'Branch', 'icon' => 'ri-git-branch-line', 'route' => 'settings.branches.index'],
    ];
@endphp

<ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item">
            <a href="{{ route($tab['route']) }}" class="nav-link {{ $active === $key ? 'active' : '' }}"
                @if ($active === $key) aria-current="page" @endif>
                <i class="{{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
            </a>
        </li>
    @endforeach
</ul>
