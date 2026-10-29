@extends('layouts.dashboard')

@section('title', 'Beli Paket')

@use('App\Support\Apps')

@php
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
@endphp

@section('content')
    @include('profile.packages._styles')

    @push('styles')
        <style>
            /* Kartu paket (29 Okt 2026), selaras dengan halaman harga website. */
            .pkgx-card { --pkgx-primary: var(--bs-primary, #df5b4f); --pkgx-primary-rgb: var(--bs-primary-rgb, 223, 91, 79); position: relative; background: #fff; color: #1f1a17; border: 1px solid rgba(0,0,0,.08); border-radius: 24px; padding: 1.5rem; box-shadow: 0 4px 18px rgba(0,0,0,.04); transition: transform .2s ease, box-shadow .2s ease; }
            .pkgx-card:hover { transform: translateY(-6px); box-shadow: 0 10px 28px rgba(0,0,0,.09); }
            .pkgx-card--featured { background: #2b1b17; border-color: #2b1b17; color: #fff; box-shadow: 0 20px 40px rgba(43,27,23,.25); }
            .pkgx-ribbon { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); background: var(--pkgx-primary); color: #fff; font-size: .7rem; font-weight: 700; letter-spacing: .04em; padding: .35rem 1rem; border-radius: 999px; white-space: nowrap; }
            .pkgx-duration { display: inline-block; padding: .3rem .65rem; border-radius: .5rem; font-size: .75rem; font-weight: 600; background: rgba(var(--pkgx-primary-rgb), .1); color: var(--pkgx-primary); }
            .pkgx-card--featured .pkgx-duration { background: rgba(255,255,255,.12); color: #ffb4a8; }
            .pkgx-save { display: inline-block; padding: .3rem .65rem; border-radius: .5rem; font-size: .75rem; font-weight: 600; background: #d8f5e4; color: #1a8a53; }
            .pkgx-name { font-weight: 700; font-size: 1.15rem; color: inherit; }
            .pkgx-price { display: flex; align-items: baseline; gap: .25rem; }
            .pkgx-currency { font-size: 1rem; font-weight: 600; color: #6c757d; }
            .pkgx-amount { font-size: 2rem; font-weight: 800; line-height: 1.1; }
            .pkgx-muted { color: #6c757d; }
            .pkgx-card--featured .pkgx-muted, .pkgx-card--featured .pkgx-currency { color: rgba(255,255,255,.65); }
            .pkgx-btn-outline { border: 1.5px solid #1a1a1a; color: #1a1a1a; background: transparent; font-weight: 600; }
            .pkgx-btn-outline:hover, .pkgx-btn-outline:focus { background: #1a1a1a; border-color: #1a1a1a; color: #fff; }
            .pkgx-btn-featured { border: 1.5px solid var(--pkgx-primary); background: var(--pkgx-primary); color: #fff; font-weight: 600; }
            .pkgx-btn-featured:hover, .pkgx-btn-featured:focus, .pkgx-btn-featured:active { filter: brightness(.92); background: var(--pkgx-primary); border-color: var(--pkgx-primary); color: #fff; }
            .pkgx-features li { display: flex; align-items: flex-start; gap: .6rem; margin-bottom: .75rem; font-size: .9rem; }
            .pkgx-features li i { color: var(--pkgx-primary); font-size: 1.1rem; line-height: 1.2; }
            .pkgx-card--featured .pkgx-features li { color: rgba(255,255,255,.9); }
            .pkgx-card--featured .pkgx-features li i { color: #ffb4a8; }
            .pkgx-detail-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; margin: .5rem 0 .6rem; padding-top: .75rem; border-top: 1px dashed rgba(0,0,0,.12); }
            .pkgx-card--featured .pkgx-detail-title { color: rgba(255,255,255,.6); border-top-color: rgba(255,255,255,.2); }
            .pkgx-features--small li { font-size: .82rem; margin-bottom: .45rem; }
            .pkgx-features--small li i { font-size: 1rem; }
        </style>
    @endpush

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

        {{-- Satu tab per kategori paket; isi tab: Trial, lalu 1, 6, 12 bulan. Trial yang sudah dipakai tidak ikut dijual. --}}
        @if ($tabs->isEmpty())
            <div class="card"><div class="card-body text-center text-muted py-5">
                <i class="ri-shopping-bag-3-line fs-1 d-block mb-2 opacity-50"></i>Belum ada paket yang dijual.
            </div></div>
        @else
            <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 mb-3" role="tablist">
                @foreach ($tabs as $categoryId => $items)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-nowrap {{ $categoryId === $activeCategory ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#pkg-tab-{{ $categoryId }}" type="button" role="tab">
                            {{ $items->first()->category?->name ?? 'Paket' }}
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- 29 Okt 2026: kartu paket mengikuti desain halaman harga website (fe-konexa):
                 label durasi + Hemat X%, harga besar, setara per bulan, tombol di atas,
                 pita TERPOPULER berkartu gelap, lalu semua fitur. --}}
            <div class="tab-content">
                @foreach ($tabs as $categoryId => $items)
                    @php
                        $bulan = fn ($p) => max(1, (int) round($p->duration / 30.4));
                        $berbayar = $items->filter(fn ($p) => ! $p->is_trial && $p->price > 0);
                        $termahalPerBulan = $berbayar->max(fn ($p) => $p->price / $bulan($p)) ?: 0;
                    @endphp
                    <div class="tab-pane fade {{ $categoryId === $activeCategory ? 'show active' : '' }}" id="pkg-tab-{{ $categoryId }}" role="tabpanel">
                        <div class="row g-4 pt-3">
                            @foreach ($items as $package)
                                @php
                                    $bln = $bulan($package);
                                    $perBulan = $package->price > 0 && ! $package->is_trial ? $package->price / $bln : 0;
                                    $hemat = $termahalPerBulan > 0 && $perBulan > 0 ? (int) round((1 - $perBulan / $termahalPerBulan) * 100) : 0;
                                    $label = $package->is_trial ? 'Trial '.$package->duration.' Hari' : ($package->duration >= 28 ? $bln.' Bulan' : $package->duration.' Hari');
                                    $fitur = $package->features();
                                @endphp
                                <div class="col-sm-6 col-xl-4 d-flex">
                                    <div class="pkgx-card w-100 d-flex flex-column {{ $package->is_featured ? 'pkgx-card--featured' : '' }}">
                                        @if ($package->is_featured)
                                            <span class="pkgx-ribbon">TERPOPULER</span>
                                        @endif

                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="pkgx-duration">{{ $label }}</span>
                                            @if ($hemat > 0)
                                                <span class="pkgx-save">Hemat {{ $hemat }}%</span>
                                            @endif
                                        </div>

                                        <h5 class="pkgx-name mb-1">{{ $package->name }}</h5>

                                        <div class="pkgx-price mb-1">
                                            @if ($package->price > 0)
                                                <span class="pkgx-currency">Rp</span>
                                                <span class="pkgx-amount">{{ number_format((int) $package->price, 0, ',', '.') }}</span>
                                            @else
                                                <span class="pkgx-amount">Gratis</span>
                                            @endif
                                        </div>
                                        <p class="pkgx-muted small mb-4">
                                            Masa aktif {{ $package->duration }} hari
                                            @if ($perBulan > 0 && $bln > 1)
                                                &middot; setara {{ $rupiah(round($perBulan)) }}/bulan
                                            @endif
                                        </p>

                                        <a href="{{ route('packages.show', $package) }}" class="btn w-100 mb-4 {{ $package->is_featured ? 'pkgx-btn-featured' : 'pkgx-btn-outline' }}">
                                            {{ $package->is_trial ? 'Coba Gratis' : 'Pilih Paket' }}
                                        </a>

                                        <ul class="pkgx-features list-unstyled mb-0 flex-grow-1">
                                            @foreach (array_slice($fitur, 0, 5) as $feature)
                                                <li><i class="ri-checkbox-circle-fill"></i><span>{{ $feature }}</span></li>
                                            @endforeach
                                        </ul>
                                        @if (count($fitur) > 5)
                                            <div class="pkgx-detail-title">Rincian fitur</div>
                                            <ul class="pkgx-features pkgx-features--small list-unstyled mb-0">
                                                @foreach (array_slice($fitur, 5) as $feature)
                                                    <li><i class="ri-check-line"></i><span>{{ $feature }}</span></li>
                                                @endforeach
                                            </ul>
                                        @endif
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
