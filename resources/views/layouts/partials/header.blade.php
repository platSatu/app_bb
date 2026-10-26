<header class="app-header" id="appHeader">
    <div class="container-fluid w-100 px-0 px-md-2">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center gap-2">
                <a href="{{ route('dashboard') }}" class="align-items-center logo-main d-none me-5 gap-2">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/favicon.png">
                    <h3 class="text-white text-opacity-80 mb-0 lh-base fw-semibold">{{ config('app.name') }}</h3>
                </a>
                <button type="button" class="vertical-toggle btn text-muted rounded-circle icon-btn" id="toggleSidebar"
                    aria-label="Toggle Sidebar">
                    <i class="ri-menu-2-line fs-4 toggle-left-arrow"></i>
                    <i class="ri-close-line fs-4 toggle-right-arrow d-none"></i>
                </button>
                <button type="button"
                    class="horizontal-toggle btn text-muted rounded-circle icon-btn header-btn d-none header-menu-btn"
                    id="toggleHorizontal" aria-label="Toggle Menu">
                    <i class="ri-menu-2-line fs-5 lh-sm"></i>
                </button>
            </div>
            <div class="flex-shrink-0 d-flex align-items-center gap-1 gap-md-3">
                <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn d-none d-md-inline-flex"
                    type="button" id="toggleFullscreen" aria-label="Toggle fullscreen">
                    <i class="ri-fullscreen-line"></i>
                </button>
                @if (! Auth::user()->isPlatformTeam() && Auth::user()->canAccess('whatsapp.devices'))
                    {{-- Lonceng: chat WhatsApp yang belum dibaca, diisi public/js/notifications.js. --}}
                    <div class="dropdown pe-dropdown-mega" data-notifications data-url="{{ route('whatsapp.notifications') }}">
                        <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn position-relative"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
                            <i class="ri-notification-4-line"></i>
                            <div class="icon-dot bg-danger d-none" data-dot><span class="ping bg-danger"></span></div>
                        </button>
                        <div class="dropdown-menu dropdown-menu-clickable dropdown-mega-md dropdown-menu-end header-dropdown-menu shadow-sm border p-0">
                            <div class="card mb-0 border-0">
                                <div class="card-header pb-5">
                                    <h5 class="card-title">Notifikasi</h5>
                                    <a href="{{ route('whatsapp.devices.index') }}" class="fw-medium">Lihat Semua</a>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="nav nav-tabs-bordered nav-dark justify-content-evenly" role="tablist">
                                        @foreach (['notif-all' => 'Semua', 'notif-messages' => 'Pesan'] as $tabId => $tabLabel)
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link px-2 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#{{ $tabId }}" role="tab">
                                                    <span>{{ $tabLabel }}</span>
                                                    <span class="h-20px min-w-20px px-1 ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1" data-count>0</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content norification-tab">
                                        @foreach (['notif-all', 'notif-messages'] as $tabId)
                                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $tabId }}" role="tabpanel">
                                                <div class="notification-panel" data-simplebar style="max-height: 324px;" data-list></div>
                                                <div class="text-center text-muted p-4" data-empty>Tidak ada pesan baru.</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div id="toggleMode">
                    <button id="theme-toggle" class="btn icon-btn theme-toggle" title="Toggles light & dark theme"
                        aria-label="Switch to dark theme" aria-live="polite" type="button">
                        <svg class="sun-and-moon" aria-hidden="true" width="24" height="24"
                            viewBox="0 0 24 24">
                            <mask class="moon" id="moon-mask">
                                <rect x="0" y="0" width="100%" height="100%" fill="white" />
                                <circle cx="24" cy="10" r="6" fill="black" />
                            </mask>
                            <circle class="sun" cx="12" cy="12" r="6" mask="url(#moon-mask)"
                                fill="currentColor" />
                            <g class="sun-beams" stroke="currentColor">
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </g>
                        </svg>
                    </button>
                </div>
                <div class="dropdown pe-dropdown-mega">
                    <button class="btn p-0 gap-2 text-start d-flex align-items-center" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="text-end mt-2 d-none d-md-block">
                            <span class="user-name d-block lh-1 fs-14">{{ Auth::user()->name }}</span>
                            <span class="text-muted fw-normal fs-13">{{ Auth::user()->email }}</span>
                        </span>
                        <span class="story-ring h-40px w-40px rounded-circle d-flex justify-content-center align-items-center">
                            <span class="h-36px w-36px rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold">
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-mega-xs dropdown-menu-end header-dropdown-menu shadow-sm border">
                        @php($headerReferral = app(\App\Services\Referral\ReferralService::class)->codeFor(Auth::user()))
                        <div class="mb-2 py-2 px-4 border rounded-2">
                            <h6 class="mb-1 fs-14">{{ Auth::user()->name }}</h6>
                            <div class="d-flex align-items-center gap-1 mb-1" data-referral-share data-link="{{ $headerReferral->link() }}">
                                <span class="badge bg-primary-subtle text-primary fw-semibold fs-10 text-nowrap">{{ $headerReferral->code }}</span>
                                <button type="button" class="btn btn-icon btn-sm p-0 text-muted" data-copy title="Salin link referral" style="width: 18px; height: 18px; line-height: 1;">
                                    <i class="ri-file-copy-line fs-11"></i>
                                </button>
                                <button type="button" class="btn btn-icon btn-sm p-0 text-muted d-none" data-share title="Bagikan link referral" style="width: 18px; height: 18px; line-height: 1;">
                                    <i class="ri-share-forward-line fs-11"></i>
                                </button>
                            </div>
                            <p class="mb-0 text-muted fs-13">{{ Auth::user()->email }}</p>
                            <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                <span class="fw-semibold fs-12 text-body text-nowrap">Rp {{ number_format((int) Auth::user()->wallet?->balance, 0, ',', '.') }}</span>
                                <a href="{{ route('account.deposits.create') }}" class="btn btn-primary text-nowrap" style="padding: .15rem .5rem; font-size: .6875rem; line-height: 1.4;">
                                    <i class="ri-add-line"></i> Tambah Saldo
                                </a>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-0">
                            <li class="profile-item">
                                <a href="{{ route('profile.edit') }}" class="text-body">
                                    <i class="ri-user-line me-3"></i>My Account
                                </a>
                            </li>
                            @unless (Auth::user()->isPlatformTeam())
                                <li class="profile-item">
                                    <a href="{{ route('packages.catalog') }}" class="text-body">
                                        <i class="ri-vip-crown-line me-3"></i>Beli Paket
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('packages.redeem') }}" class="text-body">
                                        <i class="ri-coupon-2-line me-3"></i>Redeem Voucher
                                    </a>
                                </li>
                            @endunless
                            <li class="profile-item">
                                <a href="{{ route('profile.history') }}" class="text-body">
                                    <i class="ri-history-line me-3"></i>History
                                </a>
                            </li>
                            <li class="profile-item">
                                <a href="{{ route('settings.company.show') }}" class="text-body">
                                    <i class="ri-settings-3-line me-3"></i>Settings
                                </a>
                            </li>
                            <li class="profile-item">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-danger text-decoration-none">
                                        <i class="ri-logout-box-r-line me-3"></i>Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

@if (! Auth::user()->isPlatformTeam() && Auth::user()->canAccess('whatsapp.devices'))
    @push('scripts')
        <script src="{{ asset('js/notifications.js') }}"></script>
    @endpush
@endif
