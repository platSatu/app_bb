{{-- Produk yang ditarik dari toko marketplace (semua channel). Controller: StoreProductController@index --}}
@extends('layouts.dashboard')

@section('title', 'Produk '.$store->channelLabel())
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('marketplace.shared._store-nav', ['active' => 'products'])

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <form method="GET" class="d-flex gap-2 flex-grow-1" style="max-width: 520px">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama / SKU" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                    @if (request('search'))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </form>
                @if ($canManage)
                    <form method="POST" action="{{ route($routes.'.marketplace.'.$store->channel.'.products.sync', [$page, $store]) }}"
                        onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = 'Menarik produk...';">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="ri-download-cloud-2-line"></i> Tarik Produk dari {{ $store->channelLabel() }}</button>
                    </form>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 860px">
                    <thead class="table-light">
                        <tr>
                            <th>Produk</th>
                            <th>SKU</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Stok</th>
                            <th>Status</th>
                            <th class="text-end">Jual juga di</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($product->image)
                                            <img src="{{ $product->image }}" alt="" width="44" height="44" class="rounded border" style="object-fit: cover;" loading="lazy" referrerpolicy="no-referrer">
                                        @else
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;"><i class="ri-image-line text-muted"></i></div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($product->name, 80) }}</div>
                                            <div class="small text-muted">Item ID {{ $product->external_id }}
                                                @if ($product->url) · <a href="{{ $product->url }}" target="_blank" rel="noopener">Lihat di {{ $store->channelLabel() }} <i class="ri-external-link-line"></i></a> @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">{{ $product->sku ?? '-' }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($product->isDiscounted())
                                        <div class="small text-muted text-decoration-line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</div>
                                    @endif
                                    <span class="fw-semibold">Rp {{ number_format($product->finalPrice(), 0, ',', '.') }}</span>
                                </td>
                                <td class="text-center">{{ $product->stock }}</td>
                                <td><span class="badge {{ $product->statusBadge() }}">{{ $product->status ?? '-' }}</span></td>
                                <td class="text-end text-nowrap">
                                    @foreach ($resellChannels as $channel)
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" disabled title="Jual ulang ke {{ $channel['label'] }} segera hadir">
                                            <i class="{{ $channel['icon'] }}"></i> {{ $channel['label'] }}
                                        </button>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                Belum ada produk. Klik "Tarik Produk dari {{ $store->channelLabel() }}" untuk mengambil produk toko ini.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $products->links() }}</div>
        </div>
    </div>
@endsection
