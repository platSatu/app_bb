{{-- Tab halaman (gaya sama dengan tab Invoice). $tabs = [key => [label, icon, url]], $active = key aktif. --}}
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap">
    @foreach ($tabs as $key => [$label, $icon, $url])
        <li class="nav-item">
            <a href="{{ $url }}" class="nav-link {{ $active === $key ? 'active' : '' }}" @if ($active === $key) aria-current="page" @endif><i class="{{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
    @endforeach
</ul>
