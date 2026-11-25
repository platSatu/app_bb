{{-- Form upload produk katalog ke TikTok Shop. Controller: TiktokPublishController. Script: public/js/marketplace/tiktok-form.js --}}
@extends('layouts.dashboard')

@section('title', 'Upload ke TikTok Shop')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php($channelRoute = $routes.'.marketplace.tiktok')

@section('content')
    @include('marketplace.shared._store-nav', ['active' => 'publish'])

    <form method="POST" action="{{ route($channelRoute.'.publish.store', [$page, $store, $product]) }}" id="marketplace-publish"
        data-categories-url="{{ route($channelRoute.'.categories', [$page, $store]) }}"
        data-suggest-url="{{ route($channelRoute.'.categories.suggest', [$page, $store]) }}"
        data-attributes-url="{{ route($channelRoute.'.categories.attributes', [$page, $store, '__ID__']) }}"
        data-brands-url="{{ route($channelRoute.'.categories.brands', [$page, $store, '__ID__']) }}"
        data-product-name="{{ $product->name }}"
        data-old-attributes='@json(old('attributes', []))'
        data-old-brand="{{ old('brand_id') }}">
        @csrf

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-body">
                        @include('marketplace.shared._publish-header')

                        <h6 class="mb-2">1. Kategori TikTok Shop</h6>
                        @include('marketplace.shared._category-picker')

                        <h6 class="mb-2">2. Atribut kategori</h6>
                        <div class="small text-muted mb-2" data-attributes-hint>Pilih kategori dulu, atribut yang diminta TikTok Shop akan muncul di sini. Yang bertanda <span class="text-danger">*</span> wajib.</div>
                        <div class="row g-2 mb-3" data-attributes></div>

                        <h6 class="mb-2">3. Brand</h6>
                        <div class="row g-2 mb-1">
                            <div class="col-12 col-md-5">
                                <input type="text" class="form-control" placeholder="Cari brand..." data-brand-search>
                            </div>
                            <div class="col-12 col-md-7">
                                <select name="brand_id" class="form-select" data-brand-select>
                                    <option value="">Tanpa brand (No brand)</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text" data-brand-hint>Daftar brand dimuat setelah kategori dipilih. Produk tanpa merek / tahap uji coba: pilih "Tanpa brand".</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">4. Harga & stok</h6>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Seller SKU</label>
                            <input type="text" name="seller_sku" class="form-control" maxlength="50" value="{{ old('seller_sku', \Illuminate\Support\Str::limit($defaultSku, 50, '')) }}">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga ({{ $currency }}) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="1" value="{{ old('price', $product->finalPrice()) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Stok <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" min="0" value="{{ old('quantity', $product->stock ?? 10) }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1">Gudang <span class="text-danger">*</span></label>
                            @if ($warehouseError)
                                <div class="alert alert-warning small mb-1">Daftar gudang belum bisa dimuat: {{ $warehouseError }}</div>
                            @elseif ($warehouses === [])
                                <div class="alert alert-warning small mb-1">Toko belum punya gudang penjualan aktif. Atur dulu di TikTok Shop Seller Center.</div>
                            @endif
                            <select name="warehouse_id" class="form-select" required>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse['id'] }}" @selected(old('warehouse_id') === $warehouse['id'])>{{ $warehouse['name'] }}{{ $warehouse['default'] ? ' (default)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <h6 class="mb-2">5. Paket</h6>
                        @include('marketplace.shared._package-fields')

                        <button type="submit" class="btn btn-success w-100 mt-3" @disabled($warehouses === [])><i class="ri-upload-2-line"></i> Upload ke TikTok Shop</button>
                        <div class="form-text">Foto dikirim langsung dari server bizbos. Setelah dibuat, produk diaudit TikTok Shop sebelum tampil.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/marketplace/category-picker.js') }}?v={{ filemtime(public_path('js/marketplace/category-picker.js')) }}"></script>
    <script src="{{ asset('js/marketplace/tiktok-form.js') }}?v={{ filemtime(public_path('js/marketplace/tiktok-form.js')) }}"></script>
@endpush
