{{--
    Header toko + sub-tab, dipakai semua halaman toko marketplace (semua channel).
    Butuh: $page, $store, $routes. $active: products | publish
--}}
@php($channelRoute = $routes.'.marketplace.'.$store->channel)

@include('page.category._tabs', ['active' => 'catalog-marketplace'])

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route($routes.'.marketplace.index', $page) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke Marketplace"><i class="ri-arrow-left-line"></i></a>
        <div>
            <div class="fw-semibold">
                <i class="{{ config('marketplace.channels.'.$store->channel.'.icon') }}"></i>
                {{ $store->channelLabel() }} · {{ $store->displayName() }}
                @if ($store->app?->isSandbox()) <span class="badge bg-warning-subtle text-warning">Sandbox</span> @endif
            </div>
            <div class="small text-muted">
                <span class="badge {{ $store->statusBadge() }}">{{ $store->statusLabel() }}</span>
                {{ strtoupper((string) $store->country) }}{{ $store->account ? ' · '.$store->account : '' }}
                @if ($store->last_synced_at) · sinkron terakhir {{ $store->last_synced_at->diffForHumans() }} @endif
            </div>
        </div>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a href="{{ route($channelRoute.'.products.index', [$page, $store]) }}" class="nav-link {{ $active === 'products' ? 'active' : '' }}">
                <i class="ri-shopping-bag-3-line me-1"></i>Produk di {{ $store->channelLabel() }}
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route($channelRoute.'.publish.index', [$page, $store]) }}" class="nav-link {{ $active === 'publish' ? 'active' : '' }}">
                <i class="ri-upload-2-line me-1"></i>Upload ke {{ $store->channelLabel() }}
            </a>
        </li>
    </ul>
</div>
