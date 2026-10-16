<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto text-nowrap">
    @foreach (\App\Http\Controllers\Schedule\ScheduleResourceController::tabs() as $key => $tab)
        <li class="nav-item">
            <a href="{{ route($routes.'.'.$key.'.index') }}" class="nav-link {{ $resource === $key ? 'active' : '' }}" @if ($resource === $key) aria-current="page" @endif>
                <i class="{{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
            </a>
        </li>
    @endforeach
</ul>
