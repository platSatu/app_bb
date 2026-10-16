@extends('layouts.dashboard')

@section('title', 'Pesanan Tiket')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-orders'])

    <div class="row g-3 mb-1">
        @foreach ([
            ['Pesanan lunas', number_format($summary->orders ?? 0, 0, ',', '.'), 'ri-checkbox-circle-line text-success'],
            ['Tiket terjual', number_format($ticketsSold, 0, ',', '.'), 'ri-coupon-3-line text-primary'],
            ['Pendapatan', 'Rp '.number_format($summary->revenue ?? 0, 0, ',', '.'), 'ri-money-dollar-circle-line text-warning'],
        ] as [$label, $value, $icon])
            <div class="col-md-4">
                <div class="card mb-0">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="{{ $icon }} fs-2"></i>
                        <div><div class="text-muted small">{{ $label }}</div><div class="fs-5 fw-semibold">{{ $value }}</div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md"><input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nomor pesanan, nama, email, atau WhatsApp" value="{{ request('search') }}"></div>
                <div class="col-12 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\TicketOrder::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'status'])) <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a> @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Pesanan</th><th>Pembeli</th><th class="text-center">Tiket</th><th class="text-end">Total</th><th>Status</th><th>Waktu</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $order->reference }}</td>
                                <td>{{ $order->name }}<div class="text-muted small">{{ $order->email }}</div></td>
                                <td class="text-center">{{ (int) $order->ticket_count }}</td>
                                <td class="text-end text-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                <td><span class="badge {{ $order->statusBadge() }}">{{ $order->statusLabel() }}</span></td>
                                <td class="text-nowrap small">{{ $order->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end"><a href="{{ route($routes.'.ticket.orders.show', [$page, $order]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'status']) ? 'Tidak ada pesanan yang cocok.' : 'Belum ada pesanan. Bagikan link atau QR code event Anda.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $orders->links() }}</div>
        </div>
    </div>
@endsection
