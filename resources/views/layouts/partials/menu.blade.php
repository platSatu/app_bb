<aside class="pe-app-sidebar" id="sidebar">
    <div class="pe-app-sidebar-logo px-5 d-flex align-items-center position-relative">
        <a href="{{ route('dashboard') }}" class="d-flex gap-2 logo-main">
            <img height="33" width="33" class="logo-dark" alt="Logo" src="{{ asset('be') }}/assets/images/favicon.png">
            <h3 class="text-white text-opacity-80 mb-0 lh-base fw-semibold">{{ config('app.name') }}</h3>
        </a>
        <button type="button" id="sidebarDefaultArrow"
            class="btn btn-sm p-0 fs-4 ms-auto float-end d-none icon-hover-btn text-white text-opacity-60">
            <i class="ri-arrow-right-double-line"></i>
        </button>
    </div>
    <nav class="pe-app-sidebar-menu nav nav-pills" data-simplebar id="sidebar-simplebar">
        <div class="d-flex align-items-start flex-column w-100">
            <ul class="pe-main-menu list-unstyled">
                <li class="pe-menu-title">Main</li>
                <li class="pe-slide">
                    <a href="{{ route('dashboard') }}" class="pe-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="uil uil-tachometer-fast-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Dashboard</span>
                    </a>
                </li>

                @foreach (\App\Support\Menu::visibleSections(auth()->user()) as $sectionKey => $section)
                    <li class="pe-menu-title">{{ $section['label'] }}</li>
                    @foreach ($section['groups'] as $groupKey => $group)
                        @php
                            $collapseId = 'menu-'.$sectionKey.'-'.$groupKey;
                            $isActive = fn ($items) => $items->contains(fn ($item) => request()->routeIs($item['active']));
                            $groupActive = $isActive($group['items']) || $group['submenus']->contains(fn ($sub) => $isActive($sub['items']));
                        @endphp
                        <li class="pe-slide pe-has-sub">
                            <a href="#{{ $collapseId }}" class="pe-nav-link {{ $groupActive ? 'active' : 'collapsed' }}" data-bs-toggle="collapse"
                                aria-expanded="{{ $groupActive ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                                <i class="{{ $group['icon'] }} pe-nav-icon"></i>
                                <span class="pe-nav-content">{{ $group['label'] }}</span>
                                <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                                <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                            </a>
                            <ul class="pe-slide-menu collapse {{ $groupActive ? 'show' : '' }}" id="{{ $collapseId }}">
                                @foreach ($group['items'] as $item)
                                    <li class="pe-slide-item">
                                        <a href="{{ route($item['route']) }}" class="pe-nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                                            {{ $item['label'] }}
                                        </a>
                                    </li>
                                @endforeach

                                @foreach ($group['submenus'] as $subKey => $submenu)
                                    @php
                                        $subId = $collapseId.'-'.$subKey;
                                        $subActive = $isActive($submenu['items']);
                                    @endphp
                                    <li class="pe-slide-item pe-has-sub">
                                        <a href="#{{ $subId }}" class="pe-nav-link {{ $subActive ? 'active' : 'collapsed' }}" data-bs-toggle="collapse"
                                            aria-expanded="{{ $subActive ? 'true' : 'false' }}" aria-controls="{{ $subId }}">
                                            {{ $submenu['label'] }}
                                            <i class="ri-arrow-down-s-line pe-nav-arrow ms-auto"></i>
                                        </a>
                                        <ul class="pe-slide-menu collapse ps-3 {{ $subActive ? 'show' : '' }}" id="{{ $subId }}">
                                            @foreach ($submenu['items'] as $item)
                                                <li class="pe-slide-item">
                                                    <a href="{{ route($item['route']) }}" class="pe-nav-link {{ request()->routeIs($item['active']) ? 'active' : '' }}">
                                                        {{ $item['label'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                @endforeach

                @unless (auth()->user()->isPlatformTeam())
                    @include('layouts.partials.menu-portal')
                @endunless

                <li class="pe-slide">
                    <form id="sidebar-logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                        @csrf
                    </form>
                    <a href="{{ route('logout') }}" class="pe-nav-link"
                        onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
                        <i class="uil uil-sign-out-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Keluar</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>
