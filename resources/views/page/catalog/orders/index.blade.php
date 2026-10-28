@extends('layouts.dashboard')

@section('title', 'Pesanan')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-orders'])

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nomor pesanan, nama, atau WhatsApp" value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\CatalogOrder::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pesanan</th>
                            <th>Pemesan</th>
                            <th class="text-center">Item</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $order->reference }}</td>
                                <td>
                                    {{ $order->name }}
                                    <div class="text-muted small">{{ $order->phone }}</div>
                                </td>
                                <td class="text-center">{{ $order->items_count }}</td>
                                <td class="text-end text-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td><span class="badge {{ $order->statusBadge() }}">{{ $order->statusLabel() }}</span></td>
                                <td class="text-nowrap small">{{ $order->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route($routes.'.catalog.orders.show', [$page, $order]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'status']) ? 'Tidak ada pesanan yang cocok.' : 'Belum ada pesanan. Bagikan link atau QR code toko Anda.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $orders->links() }}</div>
        </div>
    </div>
@endsection
