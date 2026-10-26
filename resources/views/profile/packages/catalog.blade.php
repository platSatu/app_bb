@extends('layouts.dashboard')

@section('title', 'Beli Paket')

@use('App\Support\Apps')

@php($rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))

@section('content')
    @include('profile.packages._styles')

    <div class="pb-4">
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <span class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="ri-wallet-3-line fs-4"></i></span>
                <div class="flex-grow-1">
                    <div class="small text-muted">Saldo Anda</div>
                    <div class="fs-5 fw-semibold">{{ $rupiah($balance) }}</div>
                </div>
                <a href="{{ route('account.deposits.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Saldo</a>
                <a href="{{ route('packages.redeem') }}" class="btn btn-outline-secondary"><i class="ri-coupon-2-line"></i> Punya kode voucher?</a>
            </div>
        </div>

        @if ($categories->count() > 1)
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="{{ route('packages.catalog') }}" class="btn btn-sm rounded-pill {{ $activeCategory ? 'btn-light' : 'btn-dark' }}">Semua</a>
                @foreach ($categories as $category)
                    <a href="{{ route('packages.catalog', ['kategori' => $category->id]) }}" class="btn btn-sm rounded-pill {{ $activeCategory === $category->id ? 'btn-dark' : 'btn-light' }}">{{ $category->name }}</a>
                @endforeach
            </div>
        @endif

        {{-- Tab per durasi (Trial, 1/6/12 bulan); trial yang sudah dipakai tidak ikut dijual, jadi tab-nya hilang. --}}
        @php($tabs = $packages->groupBy(fn ($p) => $p->is_trial ? 0 : $p->duration)->sortKeys())
        @php($tabLabel = fn ($days) => [0 => 'Trial', 30 => '1 Bulan', 180 => '6 Bulan', 365 => '12 Bulan'][$days] ?? $days.' Hari')

        @if ($tabs->isEmpty())
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="ri-shopping-bag-3-line fs-1 d-block mb-2 opacity-50"></i>Belum ada paket yang dijual.
            </div></div>
        @else
            <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 mb-3" role="tablist">
                @foreach ($tabs as $days => $items)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#pkg-tab-{{ $days }}" type="button" role="tab">
                            {{ $tabLabel($days) }}
                            @if ($items->contains('is_featured', true)) <i class="ri-star-fill text-warning"></i> @endif
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach ($tabs as $days => $items)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="pkg-tab-{{ $days }}" role="tabpanel">
                        <div class="row g-3">
                            @foreach ($items as $package)
                                <div class="col-sm-6 col-xl-4">
                                    <div class="card pkg-card h-100 mb-0 {{ $package->is_trial ? 'is-trial' : '' }} {{ $package->is_featured ? 'border border-warning' : '' }}">
                                        <div class="pkg-head">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <span class="small text-muted">{{ $package->category?->name }}</span>
                                                @if ($package->is_trial) <span class="badge bg-info">Gratis</span> @endif
                                                @if ($package->is_featured) <span class="badge bg-warning text-dark"><i class="ri-star-fill"></i> Populer</span> @endif
                                            </div>
                                            <h5 class="mb-2">{{ $package->name }}</h5>
                                            <div class="pkg-price text-primary">{{ $package->price ? $rupiah($package->price) : 'Gratis' }}</div>
                                            <div class="small text-muted">untuk {{ $package->duration }} hari</div>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex flex-wrap gap-1 mb-3">
                                                @foreach ($package->apps as $key)
                                                    <span class="pkg-app"><i class="{{ Apps::LIST[$key]['icon'] ?? 'ri-apps-line' }}"></i>{{ Apps::label($key) }}</span>
                                                @endforeach
                                            </div>
                                            @foreach (array_slice($package->features(), 0, 5) as $feature)
                                                <div class="pkg-feature"><i class="ri-check-line"></i><span>{{ $feature }}</span></div>
                                            @endforeach
                                            <a href="{{ route('packages.show', $package) }}" class="btn {{ $package->is_trial ? 'btn-info' : 'btn-primary' }} w-100 mt-auto">
                                                {{ $package->is_trial ? 'Coba Gratis' : 'Pilih Paket' }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
