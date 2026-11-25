{{-- Form upload produk katalog ke Lazada. Controller: LazadaPublishController. Script: public/js/marketplace/lazada-form.js --}}
@extends('layouts.dashboard')

@section('title', 'Upload ke Lazada')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php($channelRoute = $routes.'.marketplace.lazada')

@section('content')
    @include('marketplace.shared._store-nav', ['active' => 'publish'])

    <form method="POST" action="{{ route($channelRoute.'.publish.store', [$page, $store, $product]) }}" id="marketplace-publish"
        data-categories-url="{{ route($channelRoute.'.categories', [$page, $store]) }}"
        data-suggest-url="{{ route($channelRoute.'.categories.suggest', [$page, $store]) }}"
        data-attributes-url="{{ route($channelRoute.'.categories.attributes', [$page, $store, '__ID__']) }}"
        data-product-name="{{ $product->name }}"
        data-product-image="{{ ($cover = $product->cover()) ? url($cover->url()) : '' }}"
        data-old-attributes='@json(old('attributes', []))'
        data-old-sku-attributes='@json(old('sku_attributes', []))'>
        @csrf

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-body">
                        @include('marketplace.shared._publish-header')

                        <h6 class="mb-2">1. Kategori Lazada</h6>
                        @include('marketplace.shared._category-picker')

                        <h6 class="mb-2">2. Atribut kategori</h6>
                        <div class="small text-muted mb-2" data-attributes-hint>Pilih kategori dulu, atribut yang diminta Lazada akan muncul di sini. Yang bertanda <span class="text-danger">*</span> wajib.</div>
                        <div class="row g-2 mb-3" data-attributes></div>

                        <h6 class="mb-2">3. Brand</h6>
                        <div class="row g-2 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label small mb-1">Brand ID Lazada <span class="text-danger">*</span></label>
                                <input type="text" name="brand_id" class="form-control" inputmode="numeric" value="{{ old('brand_id') }}" required placeholder="mis. ID untuk No Brand">
                            </div>
                        </div>
                        <div class="form-text">Produk tanpa merek: pakai Brand ID "No Brand" di negara toko ini (lihat di Seller Center). API Lazada tidak menyediakan pencarian brand berdasarkan nama.</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">4. Harga & stok</h6>
                        <div class="mb-2">
                            <label class="form-label small mb-1">SKU penjual <span class="text-danger">*</span></label>
                            <input type="text" name="seller_sku" class="form-control" maxlength="100" value="{{ old('seller_sku', $defaultSku) }}" required>
                            <div class="form-text">Harus unik di toko Lazada ini.</div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="1000" value="{{ old('price', $product->price) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga promo (Rp)</label>
                                <input type="number" name="special_price" class="form-control" min="1000" value="{{ old('special_price', $product->isDiscounted() ? $product->sale_price : null) }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1">Stok di Lazada <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0" value="{{ old('quantity', $product->stock ?? 10) }}" required>
                        </div>

                        <h6 class="mb-2">5. Paket</h6>
                        @include('marketplace.shared._package-fields')
                        <div class="mt-2">
                            <label class="form-label small mb-1">Isi paket</label>
                            <input type="text" name="package_content" class="form-control" maxlength="200" value="{{ old('package_content') }}" placeholder="mis. 1 pcs kaos">
                        </div>

                        <button type="submit" class="btn btn-success w-100 mt-3"><i class="ri-upload-2-line"></i> Upload ke Lazada</button>
                        <div class="form-text">Lazada menyalin foto dari URL bizbos, jadi alamat foto harus bisa dibuka dari internet (tidak bisa dari localhost).</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/marketplace/category-picker.js') }}?v={{ filemtime(public_path('js/marketplace/category-picker.js')) }}"></script>
    <script src="{{ asset('js/marketplace/lazada-form.js') }}?v={{ filemtime(public_path('js/marketplace/lazada-form.js')) }}"></script>
@endpush
