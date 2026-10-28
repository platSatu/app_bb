@php
    $tabs = ['info' => ['Informasi', 'ri-information-line', route($routes.'.edit', $page)]];

    foreach ($page->templateConfig('tabs') ?? [] as $key => [$label, $icon, $route]) {
        $tabs[$key] = [$label, $icon, route($routes.'.'.$route, $page)];
    }
@endphp

<div class="d-flex justify-content-end mb-2">
    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
        <i class="ri-external-link-line"></i> Lihat {{ in_array($routes, ['events', 'superadmin.events'], true) ? 'Event' : 'Page' }}
    </a>
</div>

{{-- Tab digeser ke samping (tidak turun ke baris bawah); tab aktif otomatis terlihat. --}}
<div class="page-tabs mb-3">
    <ul class="nav nav-tabs mb-0 flex-nowrap text-nowrap">
        @foreach ($tabs as $key => [$label, $icon, $url])
            <li class="nav-item">
                <a href="{{ $url }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif>
                    <i class="{{ $icon }} me-1"></i>{{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

@once
    @push('styles')
        <style>
            .page-tabs { overflow-x: auto; overflow-y: hidden; scrollbar-width: thin; -webkit-overflow-scrolling: touch; }
            .page-tabs::-webkit-scrollbar { height: 4px; }
            .page-tabs::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); border-radius: 4px; }
        </style>
    @endpush
    @push('scripts')
        <script>
            document.querySelectorAll('.page-tabs .nav-link.active').forEach(function (tab) {
                tab.scrollIntoView({ block: 'nearest', inline: 'center' });
            });
        </script>
    @endpush
@endonce
