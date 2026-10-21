@php
    $tabs = ['info' => ['Informasi', 'ri-information-line', route($routes.'.edit', $page)]];

    foreach ($page->templateConfig('tabs') ?? [] as $key => [$label, $icon, $route]) {
        $tabs[$key] = [$label, $icon, route($routes.'.'.$route, $page)];
    }

    // Tombol pengaturan tambahan di kanan tab (mis. Syarat & Ketentuan tiket).
    $action = $page->templateConfig('action');
@endphp

<div class="d-flex justify-content-end mb-2">
    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="ri-external-link-line"></i> Lihat Page
    </a>
</div>

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
    @if ($action)
        @php([$key, $label, $icon, $route] = $action)
        <a href="{{ route($routes.'.'.$route, $page) }}" class="btn btn-sm {{ $active === $key ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="{{ $icon }}"></i> {{ $label }}
        </a>
    @endif
</div>
