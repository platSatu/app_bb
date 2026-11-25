{{-- Header toko Lazada + sub-tab. Dipakai halaman Produk Lazada & Upload ke Lazada. $active: products | publish --}}
@include('page.category._tabs', ['active' => 'catalog-marketplace'])

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route($routes.'.marketplace.index', $page) }}" class="btn btn-sm btn-outline-secondary" title="Kembali ke Marketplace"><i class="ri-arrow-left-line"></i></a>
        <div>
            <div class="fw-semibold">Lazada · {{ $store->displayName() }}</div>
            <div class="small text-muted">
                <span class="badge {{ $store->statusBadge() }}">{{ $store->statusLabel() }}</span>
                {{ strtoupper((string) $store->country) }} · {{ $store->account }}
                @if ($store->last_synced_at) · sinkron terakhir {{ $store->last_synced_at->diffForHumans() }} @endif
            </div>
        </div>
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a href="{{ route($routes.'.marketplace.lazada.products.index', [$page, $store]) }}" class="nav-link {{ $active === 'products' ? 'active' : '' }}"><i class="ri-shopping-bag-3-line me-1"></i>Produk di Lazada</a>
        </li>
        <li class="nav-item">
            <a href="{{ route($routes.'.marketplace.lazada.publish.index', [$page, $store]) }}" class="nav-link {{ $active === 'publish' ? 'active' : '' }}"><i class="ri-upload-2-line me-1"></i>Upload ke Lazada</a>
        </li>
    </ul>
</div>
