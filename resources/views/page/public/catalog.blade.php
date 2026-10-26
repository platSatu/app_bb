@if ($page->description)
    <p class="text-muted text-center mb-4" style="white-space: pre-line;">{{ $page->description }}</p>
@endif

@if ($categories->isNotEmpty())
    <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
        <a href="{{ $page->url() }}" class="btn btn-sm rounded-pill {{ $activeCategory ? 'btn-outline-secondary' : 'btn-dark' }}">Semua</a>
        @foreach ($categories as $category)
            <a href="{{ $page->url() }}?kategori={{ $category->slug }}" class="btn btn-sm rounded-pill {{ $activeCategory?->is($category) ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $category->name }}</a>
        @endforeach
    </div>
@endif

<div class="row g-3 mb-5 pb-5">
    @forelse ($products as $product)
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm">
                <a href="{{ route('page.product', [$page->slug, $product->slug]) }}">
                    @if ($cover = $product->cover())
                        <img src="{{ $cover->thumbnailUrl() }}" alt="{{ $cover->name ?: $product->name }}" class="card-img-top product-thumb" loading="lazy" width="400" height="400">
                    @else
                        <div class="card-img-top product-thumb bg-light d-flex align-items-center justify-content-center"><i class="ri-image-line fs-1 text-muted"></i></div>
                    @endif
                </a>
                <div class="card-body p-2 p-md-3 d-flex flex-column">
                    <a href="{{ route('page.product', [$page->slug, $product->slug]) }}" class="text-body fw-semibold small mb-1">{{ $product->name }}</a>
                    <div class="small mb-2">@include('page.public._price')</div>
                    <div class="mt-auto">@include('page.public._cart-button')</div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center text-muted py-5">Belum ada produk.</div>
    @endforelse
</div>

<div class="mb-5">{{ $products->links() }}</div>

@include('page.public._cart-bar')
