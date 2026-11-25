{{-- Form upload produk katalog ke Shopee. Controller: ShopeePublishController. Script: public/js/marketplace/shopee-form.js --}}
@extends('layouts.dashboard')

@section('title', 'Upload ke Shopee')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php($channelRoute = $routes.'.marketplace.shopee')

@section('content')
    @include('marketplace.shared._store-nav', ['active' => 'publish'])

    <form method="POST" action="{{ route($channelRoute.'.publish.store', [$page, $store, $product]) }}" id="marketplace-publish"
        data-categories-url="{{ route($channelRoute.'.categories', [$page, $store]) }}"
        data-suggest-url="{{ route($channelRoute.'.categories.suggest', [$page, $store]) }}"
        data-attributes-url="{{ route($channelRoute.'.categories.attributes', [$page, $store, '__ID__']) }}"
        data-brands-url="{{ route($channelRoute.'.categories.brands', [$page, $store, '__ID__']) }}"
        data-product-name="{{ $product->name }}"
        data-old-attributes='@json(old('attributes', []))'
        data-old-brand="{{ old('brand_id', 0) }}">
        @csrf

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-body">
                        @include('marketplace.shared._publish-header')

                        <h6 class="mb-2">1. Kategori Shopee</h6>
                        @include('marketplace.shared._category-picker')

                        <h6 class="mb-2">2. Atribut kategori</h6>
                        <div class="small text-muted mb-2" data-attributes-hint>Pilih kategori dulu, atribut yang diminta Shopee akan muncul di sini. Yang bertanda <span class="text-danger">*</span> wajib.</div>
                        <div class="row g-2 mb-3" data-attributes></div>

                        <h6 class="mb-2">3. Brand</h6>
                        <div class="row g-2 mb-1">
                            <div class="col-12 col-md-7">
                                <select name="brand_id" class="form-select" data-brand-select>
                                    <option value="0">No Brand</option>
                                </select>
                                <input type="hidden" name="brand_name" value="{{ old('brand_name') }}" data-brand-name>
                            </div>
                        </div>
                        <div class="form-text mb-3" data-brand-hint>Daftar brand dimuat setelah kategori dipilih. Produk tanpa merek: pilih No Brand.</div>

                        <h6 class="mb-2">4. Jasa kirim <span class="text-danger">*</span></h6>
                        @if ($logisticsError)
                            <div class="alert alert-warning small">Daftar jasa kirim belum bisa dimuat: {{ $logisticsError }}</div>
                        @elseif ($logistics === [])
                            <div class="alert alert-warning small">Toko ini belum mengaktifkan jasa kirim. Aktifkan dulu di Shopee Seller Center › Pengaturan Pengiriman.</div>
                        @endif
                        <div class="list-group mb-1">
                            @foreach ($logistics as $channel)
                                @php($row = old('logistics.'.$channel['id'], []))
                                <div class="list-group-item">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <input type="hidden" name="logistics[{{ $channel['id'] }}][enabled]" value="0">
                                        <div class="form-check mb-0 me-auto">
                                            <input type="checkbox" class="form-check-input" id="logistic-{{ $channel['id'] }}" name="logistics[{{ $channel['id'] }}][enabled]" value="1"
                                                @checked(old('logistics') ? ! empty($row['enabled']) : $channel['fee_type'] !== 'CUSTOM_PRICE')>
                                            <label class="form-check-label" for="logistic-{{ $channel['id'] }}">{{ $channel['name'] }}</label>
                                        </div>
                                        @if ($channel['fee_type'] === 'SIZE_SELECTION' && $channel['sizes'])
                                            <select name="logistics[{{ $channel['id'] }}][size_id]" class="form-select form-select-sm" style="max-width: 200px">
                                                @foreach ($channel['sizes'] as $size)
                                                    <option value="{{ $size['id'] }}" @selected(($row['size_id'] ?? null) == $size['id'])>{{ $size['name'] }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($channel['fee_type'] === 'CUSTOM_PRICE')
                                            <input type="number" name="logistics[{{ $channel['id'] }}][shipping_fee]" class="form-control form-control-sm" style="max-width: 160px" min="0" placeholder="Ongkir (Rp)" value="{{ $row['shipping_fee'] ?? '' }}">
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">Untuk jasa kirim berbasis ukuran, ongkir dihitung dari berat & dimensi paket di samping.</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">5. Harga & stok</h6>
                        <div class="mb-2">
                            <label class="form-label small mb-1">SKU induk</label>
                            <input type="text" name="seller_sku" class="form-control" maxlength="100" value="{{ old('seller_sku', $defaultSku) }}">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="100" value="{{ old('price', $product->finalPrice()) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Stok <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" min="0" value="{{ old('quantity', $product->stock ?? 10) }}" required>
                            </div>
                        </div>
                        <div class="form-text mb-3">Harga coret di Shopee diatur lewat Promosi Toko, jadi yang dikirim harga jual.</div>

                        <h6 class="mb-2">6. Paket</h6>
                        @include('marketplace.shared._package-fields')

                        <button type="submit" class="btn btn-success w-100 mt-3" @disabled($logistics === [])><i class="ri-upload-2-line"></i> Upload ke Shopee</button>
                        <div class="form-text">Foto dikirim langsung dari server bizbos ke Shopee (maks. 10 MB per foto, JPG/PNG).</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/marketplace/category-picker.js') }}?v={{ filemtime(public_path('js/marketplace/category-picker.js')) }}"></script>
    <script src="{{ asset('js/marketplace/shopee-form.js') }}?v={{ filemtime(public_path('js/marketplace/shopee-form.js')) }}"></script>
@endpush
