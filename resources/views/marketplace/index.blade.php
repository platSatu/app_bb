@extends('layouts.dashboard')

@section('title', 'Marketplace')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-marketplace'])

    <p class="text-muted">
        Sambungkan toko marketplace ke katalog <strong>{{ $page->name }}</strong>: lihat produk yang sudah ada di toko,
        unggah produk katalog Anda ke marketplace, dan (segera) jual ulang produk ke channel lain.
        @if ($ownerArea)
            <br><span class="small">Lazada: pilih <b>Site</b> sesuai negara toko lalu klik <b>Authorize</b> di tab otorisasi. Shopee: login akun toko, masukkan OTP, lalu <b>Confirm Authorization</b>. TikTok Shop: login akun seller lalu setujui otorisasinya.</span>
        @endif
    </p>

    <div class="row g-3">
        @foreach ($channels as $key => $channel)
            @php
                $channelStores = $stores->get($key, collect());
                $channelApps = $apps->get($key, collect());
                $enabled = $channel['enabled'] && $channel['driver'];
            @endphp
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded d-inline-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background: {{ $channel['color'] }}">
                                    <i class="{{ $channel['icon'] }} fs-4"></i>
                                </span>
                                <div>
                                    <h5 class="mb-0">{{ $channel['label'] }}</h5>
                                    <div class="small text-muted">
                                        @if (! $enabled)
                                            Segera hadir
                                        @else
                                            {{ $channelStores->count() }} toko tersambung
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($enabled && $ownerArea && $canManage)
                                @if ($channelApps->isEmpty())
                                    <span class="badge bg-warning-subtle text-warning">App {{ $channel['label'] }} belum diatur superadmin</span>
                                @else
                                    <form method="POST" action="{{ route('pages.marketplace.'.$key.'.connect', $page) }}" class="d-flex gap-2 align-items-center">
                                        @csrf
                                        @if ($channelApps->count() > 1)
                                            <select name="app_id" class="form-select form-select-sm" style="max-width: 220px" title="App yang dipakai">
                                                @foreach ($channelApps as $app)
                                                    <option value="{{ $app->id }}">{{ $app->name }}{{ $app->is_default ? ' (default)' : '' }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="ri-link"></i> Sambungkan Toko</button>
                                    </form>
                                @endif
                            @elseif (! $enabled)
                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled><i class="ri-time-line"></i> Segera hadir</button>
                            @endif
                        </div>

                        @if ($enabled)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="min-width: 760px">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Toko</th>
                                            <th>Negara</th>
                                            <th>Status</th>
                                            <th class="text-nowrap">Token berlaku s/d</th>
                                            <th class="text-center">Produk</th>
                                            @if ($ownerArea) <th class="text-end">Aksi</th> @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($channelStores as $store)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">
                                                        {{ $store->displayName() }}
                                                        @if ($store->app?->isSandbox()) <span class="badge bg-warning-subtle text-warning">Sandbox</span> @endif
                                                    </div>
                                                    <div class="small text-muted">{{ $store->account }}{{ $store->short_code ? ' · '.$store->short_code : '' }} · App: {{ $store->app?->name }}</div>
                                                </td>
                                                <td class="text-uppercase">{{ $store->country }}</td>
                                                <td>
                                                    <span class="badge {{ $store->statusBadge() }}">{{ $store->statusLabel() }}</span>
                                                    @if ($store->status_reason)
                                                        <div class="small text-muted text-truncate" style="max-width: 220px" title="{{ $store->status_reason }}">{{ $store->status_reason }}</div>
                                                    @endif
                                                </td>
                                                <td class="small text-nowrap">{{ $store->access_expires_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                                <td class="text-center">{{ $store->products_count }}</td>
                                                @if ($ownerArea)
                                                    <td class="text-end text-nowrap">
                                                        <a href="{{ route('pages.marketplace.'.$key.'.products.index', [$page, $store]) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-shopping-bag-3-line"></i> Produk</a>
                                                        <a href="{{ route('pages.marketplace.'.$key.'.publish.index', [$page, $store]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-upload-2-line"></i> Upload</a>
                                                        @if ($canManage)
                                                            <form method="POST" action="{{ route('pages.marketplace.'.$key.'.refresh', [$page, $store]) }}" class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm rounded-pill btn-outline-secondary" title="Perpanjang token"><i class="ri-refresh-line"></i></button>
                                                            </form>
                                                            <form method="POST" action="{{ route('pages.marketplace.'.$key.'.disconnect', [$page, $store]) }}" class="d-inline"
                                                                onsubmit="return confirm({{ \Illuminate\Support\Js::from('Putus toko '.$store->displayName().' dari page ini? Data produk tarikan & riwayat upload di bizbos ikut terhapus. Produk di '.$channel['label'].' tidak terhapus.') }});">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger" title="Putuskan"><i class="ri-link-unlink-m"></i></button>
                                                            </form>
                                                        @endif
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada toko {{ $channel['label'] }}. Klik "Sambungkan Toko", login dengan akun seller, lalu setujui otorisasinya.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
