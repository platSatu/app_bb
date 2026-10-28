@extends('layouts.dashboard')

@section('title', $product->exists ? 'Edit Produk' : 'Tambah Produk')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php
    // Foto lama tidak bisa dikirim ulang lewat old(), jadi thumbnail diambil dari database.
    $thumbs = $product->images->pluck('thumbnail', 'id')->map(fn ($p) => \Illuminate\Support\Facades\Storage::disk('public')->url($p));
    $rows = collect(old('images', $product->images->map(fn ($img) => $img->only(['id', 'name', 'description', 'status']))->all()))
        ->filter(fn ($row) => ! empty($row['id']))
        ->map(fn ($row) => [...$row, 'thumb' => $thumbs[$row['id']] ?? null])
        ->all();
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-products'])

    <form method="POST" enctype="multipart/form-data"
        action="{{ $product->exists ? route($routes.'.catalog.products.update', [$page, $product]) : route($routes.'.catalog.products.store', $page) }}">
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3">
                        <h6 class="mb-1 fw-semibold">{{ $product->exists ? 'Edit Produk' : 'Produk Baru' }}</h6>
                        <div class="text-muted small lh-sm">Harga dalam rupiah tanpa titik. Stok kosong = tidak dihitung.</div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nama Produk</label>
                                <input type="text" id="name" name="name" maxlength="150" value="{{ old('name', $product->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Kategori</label>
                                <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                    <option value="">— Tanpa kategori —</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="price" class="form-label">Harga (Rp)</label>
                                <input type="number" id="price" name="price" min="0" value="{{ old('price', $product->price) }}" class="form-control @error('price') is-invalid @enderror" required>
                                @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="sale_price" class="form-label">Harga Promo (opsional)</label>
                                <input type="number" id="sale_price" name="sale_price" min="0" value="{{ old('sale_price', $product->sale_price) }}" class="form-control @error('sale_price') is-invalid @enderror">
                                @error('sale_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="stock" class="form-label">Stok (opsional)</label>
                                <input type="number" id="stock" name="stock" min="0" value="{{ old('stock', $product->stock) }}" class="form-control @error('stock') is-invalid @enderror" placeholder="Kosong = tidak dihitung">
                                @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    @foreach (\App\Models\CatalogProduct::STATUS_LABELS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', $product->status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Deskripsi</label>
                                <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror" placeholder="Satu baris = satu poin, mis.&#10;Ukuran 250 ml&#10;Tanpa gula tambahan">{{ old('description', $product->description) }}</textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3">
                        <h6 class="mb-1 fw-semibold">Foto Produk</h6>
                        <div class="text-muted small lh-sm">Maksimal {{ \App\Models\CatalogProduct::MAX_IMAGES }} foto, 5 MB per foto. Foto paling atas jadi cover. Geser <i class="ri-draggable"></i> untuk mengurutkan.</div>
                    </div>
                    <div class="card-body bg-light p-3 p-md-4">
                        @error('images') <div class="alert alert-danger">{{ $message }}</div> @enderror
                        @if ($errors->has('images.*') && ! $errors->has('images'))
                            <div class="alert alert-warning small mb-2">Foto baru perlu dipilih ulang setelah ada isian yang belum sesuai.</div>
                        @endif
                        <div data-sortable id="image-rows">
                            @foreach ($rows as $i => $row)
                                @include('page.catalog.products._image-row')
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-image"><i class="ri-add-line"></i> Tambah Foto</button>
                        <template id="image-template">
                            @include('page.catalog.products._image-row', ['i' => '__INDEX__', 'row' => []])
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route($routes.'.catalog.products.index', $page) }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Produk</button>
        </div>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('image-rows'), template = document.getElementById('image-template');
            var max = {{ \App\Models\CatalogProduct::MAX_IMAGES }}, counter = 0;

            document.getElementById('add-image').addEventListener('click', function () {
                if (rows.querySelectorAll('[data-row]').length >= max) { alert('Maksimal ' + max + ' foto per produk.'); return; }
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.querySelector('[data-file]').click();
            });

            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove]');
                if (button) button.closest('[data-row]').remove();
            });

            rows.addEventListener('change', function (event) {
                if (!event.target.matches('[data-file]') || !event.target.files[0]) return;
                var row = event.target.closest('[data-row]');
                var preview = row.querySelector('[data-preview]');
                preview.src = URL.createObjectURL(event.target.files[0]);
                preview.classList.remove('d-none');
                row.querySelector('[data-placeholder]').classList.add('d-none');
            });
        })();
    </script>
@endsection
