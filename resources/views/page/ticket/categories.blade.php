@extends('layouts.dashboard')

@section('title', 'Kategori Tiket')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@php
    $rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-categories'])

    @if ($categories->isEmpty())
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="ri-coupon-3-line fs-1 d-block mb-2"></i>
                <div class="fw-semibold text-body mb-1">Belum ada kategori tiket</div>
                <div class="small mb-3">Buat dulu kategori tiket yang mau dijual, mis. VIP, Reguler, atau Early Bird.</div>
                @if ($canManage)
                    <a href="{{ route($routes.'.ticket.categories.manage', $page) }}" class="btn btn-primary btn-sm"><i class="ri-add-line me-1"></i>Buat Kategori Tiket</a>
                @endif
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3">
                <h6 class="mb-1 fw-semibold">Penjualan per Kategori Tiket</h6>
                <div class="text-muted small lh-sm">Klik angka di kolom Terjual untuk melihat siapa saja yang sudah membeli.</div>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kategori Tiket</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Dijual</th>
                                <th class="text-center">Terjual</th>
                                <th class="text-center">Menunggu Bayar</th>
                                <th class="text-center">Masih Tersedia</th>
                                <th class="text-end">Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="fw-semibold">{{ $category->name }} @unless ($category->isActive()) <span class="badge bg-light text-muted">Tidak aktif</span> @endunless</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($category->price) }}</td>
                                    <td class="text-center">{{ $category->quota }}</td>
                                    <td class="text-center"><a href="{{ route($routes.'.ticket.categories.sales', [$page, $category]) }}" class="fw-semibold">{{ $category->sold }}</a></td>
                                    <td class="text-center text-warning">{{ $category->reserved }}</td>
                                    <td class="text-center {{ $category->remaining() === 0 ? 'text-danger fw-semibold' : '' }}">{{ $category->remaining() }}</td>
                                    <td class="text-end text-nowrap">{{ $rupiah($category->revenue()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total</td>
                                <td class="text-center">{{ $categories->sum('sold') }}</td>
                                <td class="text-center">{{ $categories->sum('reserved') }}</td>
                                <td class="text-center">{{ $categories->sum(fn ($c) => $c->remaining()) }}</td>
                                <td class="text-end text-nowrap">{{ $rupiah($categories->sum(fn ($c) => $c->revenue())) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif

@endsection
