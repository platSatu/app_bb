@extends('layouts.dashboard')

@section('title', 'Produk')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-products'])

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama produk" value="{{ request('search') }}">
                </div>
                @if ($categories->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <select name="category" class="form-select">
                            <option value="">Semua kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'category']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @if ($canManage)
                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route($routes.'.catalog.products.create', $page) }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Produk</a>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th>Status</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($cover = $product->cover())
                                            <img src="{{ $cover->thumbnailUrl() }}" alt="" width="44" height="44" class="rounded border" style="object-fit: cover;" loading="lazy">
                                        @else
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;"><i class="ri-image-line text-muted"></i></div>
                                        @endif
                                        <div class="fw-semibold">{{ $product->name }}</div>
                                    </div>
                                </td>
                                <td>{{ $product->category?->name ?? '-' }}</td>
                                <td class="text-end text-nowrap">@include('page.public._price')</td>
                                <td class="text-center">
                                    @if (! $product->tracksStock())
                                        <span class="text-muted">∞</span>
                                    @else
                                        <span class="{{ $product->isSoldOut() ? 'text-danger fw-semibold' : '' }}">{{ $product->stock }}</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $product->statusBadge() }}">{{ $product->statusLabel() }}</span></td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route($routes.'.catalog.products.edit', [$page, $product]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <form method="POST" action="{{ route($routes.'.catalog.products.destroy', [$page, $product]) }}" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus produk '.$product->name.'? Fotonya ikut terhapus, pesanan lama tetap tersimpan.') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada produk. Yuk tambahkan produk pertama.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
