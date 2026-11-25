@extends('layouts.dashboard')

@section('title', 'Upload ke Lazada')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('marketplace.lazada._nav', ['active' => 'publish'])

    <div class="card">
        <div class="card-body">
            <p class="text-muted small">Pilih produk Catalog Online yang mau diunggah ke toko Lazada ini. Setelah terkirim, Lazada memeriksa (QC) produk sebelum tampil di toko.</p>

            <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 520px">
                <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama produk" value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 760px">
                    <thead class="table-light">
                        <tr>
                            <th>Produk Katalog</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th>Status di Lazada</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php($listing = $listings->get($product->id))
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
                                <td class="text-end text-nowrap">Rp {{ number_format($product->finalPrice(), 0, ',', '.') }}</td>
                                <td class="text-center">{{ $product->tracksStock() ? $product->stock : '∞' }}</td>
                                <td>
                                    @if ($listing)
                                        <span class="badge {{ $listing->statusBadge() }}">{{ $listing->statusLabel() }}</span>
                                        <div class="small text-muted">
                                            {{ $listing->isSubmitted() ? 'Item ID '.$listing->external_id.' · ' : '' }}{{ $listing->created_at->translatedFormat('d M Y H:i') }}
                                        </div>
                                        @if (! $listing->isSubmitted())
                                            <div class="small text-danger text-truncate" style="max-width: 320px" title="{{ $listing->error }}">{{ $listing->error }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">Belum diunggah</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-end">
                                        @if ($listing?->isSubmitted())
                                            <span class="small text-muted">Sudah terkirim</span>
                                        @else
                                            <a href="{{ route($routes.'.marketplace.lazada.publish.create', [$page, $store, $product]) }}" class="btn btn-sm rounded-pill btn-outline-success">
                                                <i class="ri-upload-2-line"></i> {{ $listing ? 'Coba lagi' : 'Upload' }}
                                            </a>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada produk di Catalog Online. Tambahkan dulu di tab Produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
