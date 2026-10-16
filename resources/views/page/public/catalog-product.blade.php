@extends('page.public.layout')

@section('title', $product->name.' · '.$page->name)
@section('width', '960px')

@section('content')
    <a href="{{ $page->url() }}" class="btn btn-sm btn-light mb-3"><i class="ri-arrow-left-line"></i> {{ $page->name }}</a>

    <div class="card shadow-sm mb-5">
        <div class="card-body p-3 p-md-4">
            <div class="row g-4">
                <div class="col-md-6">
                    @php($images = $product->images)
                    @if ($images->isNotEmpty())
                        <img src="{{ $images->first()->url() }}" alt="{{ $images->first()->name ?: $product->name }}" class="w-100 rounded product-thumb mb-2" id="main-image">
                        @if ($images->count() > 1)
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach ($images as $image)
                                    <img src="{{ $image->thumbnailUrl() }}" data-large="{{ $image->url() }}" alt="{{ $image->name }}" width="64" height="64"
                                        class="rounded border product-thumb" style="cursor: pointer;" loading="lazy"
                                        onclick="document.getElementById('main-image').src = this.dataset.large">
                                @endforeach
                            </div>
                        @endif
                    @else
                        <div class="product-thumb bg-light rounded d-flex align-items-center justify-content-center"><i class="ri-image-line fs-1 text-muted"></i></div>
                    @endif
                </div>
                <div class="col-md-6">
                    <h1 class="h4 mb-2">{{ $product->name }}</h1>
                    <div class="fs-5 mb-3">@include('page.public._price')</div>
                    @if ($product->tracksStock())
                        <div class="small text-muted mb-3">{{ $product->isSoldOut() ? 'Stok habis' : 'Stok tersedia: '.$product->stock }}</div>
                    @endif
                    @if ($features = $product->features())
                        <ul class="mb-4 ps-3">
                            @foreach ($features as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div style="max-width: 260px;">@include('page.public._cart-button')</div>
                </div>
            </div>
        </div>
    </div>

    @include('page.public._cart-bar')
@endsection
