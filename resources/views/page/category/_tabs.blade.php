@php
    $tabs = ['info' => ['Informasi', 'ri-information-line', route($routes.'.edit', $page)]];

    foreach ($page->templateConfig('tabs') ?? [] as $key => [$label, $icon, $route]) {
        $tabs[$key] = [$label, $icon, route($routes.'.'.$route, $page)];
    }
@endphp

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <ul class="nav nav-tabs mb-0 flex-grow-1">
        @foreach ($tabs as $key => [$label, $icon, $url])
            <li class="nav-item">
                <a href="{{ $url }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif>
                    <i class="{{ $icon }} me-1"></i>{{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="ri-external-link-line"></i> Lihat Page
    </a>
</div>
