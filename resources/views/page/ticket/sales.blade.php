@extends('layouts.dashboard')

@section('title', 'Riwayat Pembelian')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-categories'])

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="mb-1 fw-semibold">Riwayat Pembelian · {{ $category->name }}</h6>
                <div class="text-muted small lh-sm">Terjual {{ $category->sold }} dari {{ $category->quota }} · Pendapatan Rp {{ number_format($category->revenue(), 0, ',', '.') }}</div>
            </div>
            <a href="{{ route($routes.'.ticket.categories.index', $page) }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
        <div class="card-body p-3 p-md-4">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md"><input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nomor pesanan, nama, atau email" value="{{ request('search') }}"></div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request('search')) <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a> @endif
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Pesanan</th><th>Pembeli</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th><th>Dibayar</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr>
                                <td><a href="{{ route($routes.'.ticket.orders.show', [$page, $sale->order_id]) }}" class="fw-semibold">{{ $sale->order?->reference }}</a></td>
                                <td>{{ $sale->order?->name }}<div class="text-muted small">{{ $sale->order?->email }} · {{ $sale->order?->phone }}</div></td>
                                <td class="text-center">{{ $sale->quantity }}</td>
                                <td class="text-end text-nowrap">Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</td>
                                <td class="text-nowrap small">{{ $sale->order?->paid_at?->translatedFormat('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ request('search') ? 'Tidak ada pembelian yang cocok.' : 'Belum ada pembelian untuk kategori ini.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $sales->links() }}</div>
        </div>
    </div>
@endsection
