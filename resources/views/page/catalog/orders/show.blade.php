@extends('layouts.dashboard')

@section('title', 'Detail Pesanan')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-orders'])

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fw-semibold">{{ $order->reference }}</h6>
                        <div class="text-muted small lh-sm">{{ $order->created_at->translatedFormat('d F Y, H:i') }}</div>
                    </div>
                    <span class="badge {{ $order->statusBadge() }} fs-12">{{ $order->statusLabel() }}</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Produk</th><th class="text-end">Harga</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td class="text-end text-nowrap">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end text-nowrap">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><th colspan="3" class="text-end">Total</th><th class="text-end text-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</th></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Pemesan</h6></div>
                <div class="card-body p-3 p-md-4">
                    <dl class="mb-0">
                        <dt class="text-muted fw-normal small">Nama</dt><dd>{{ $order->name }}</dd>
                        <dt class="text-muted fw-normal small">WhatsApp</dt><dd>{{ $order->phone }}</dd>
                        <dt class="text-muted fw-normal small">Email</dt><dd>{{ $order->email ?: '-' }}</dd>
                        <dt class="text-muted fw-normal small">Alamat</dt><dd style="white-space: pre-line;">{{ $order->address ?: '-' }}</dd>
                        <dt class="text-muted fw-normal small">Catatan</dt><dd style="white-space: pre-line;">{{ $order->note ?: '-' }}</dd>
                        <dt class="text-muted fw-normal small">Pembayaran</dt>
                        <dd class="mb-0">
                            {{ $order->paymentLabel() }}
                            @if ($order->paid_at)
                                <div class="small text-success">Lunas {{ $order->paid_at->translatedFormat('d M Y H:i') }}{{ $order->payment_method ? ' · '.$order->payment_method : '' }} · masuk saldo Anda</div>
                            @elseif ($order->awaitsOnlinePayment() && $order->status === \App\Models\CatalogOrder::STATUS_NEW)
                                <div class="small text-warning">Menunggu pembayaran sampai {{ $order->expires_at?->translatedFormat('d M Y H:i') }}</div>
                            @endif
                        </dd>
                    </dl>
                    @if ($order->payment_note)
                        <div class="alert alert-warning small mt-3 mb-0">{{ $order->payment_note }}</div>
                    @endif
                    @if ($order->customer && auth()->user()->canAccess($routes === 'pages' ? 'pages.customers' : 'superadmin.customers'))
                        <a href="{{ route($routes === 'pages' ? 'pages.customers.show' : 'superadmin.contacts.show', $order->customer) }}" class="btn btn-sm btn-outline-secondary w-100 mt-3">
                            <i class="ri-contacts-book-line me-1"></i>Lihat di Customers
                        </a>
                    @endif
                </div>
            </div>

            @if ($canManage && ! $order->isFinal())
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Ubah Status</h6></div>
                    <div class="card-body p-3 p-md-4">
                        <form method="POST" action="{{ route($routes.'.catalog.orders.status', [$page, $order]) }}"
                            onsubmit="return this.status.value !== 'cancelled' || confirm('Batalkan pesanan ini? Stok produk akan dikembalikan dan status tidak bisa diubah lagi.');">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="form-select mb-2">
                                @foreach (\App\Models\CatalogOrder::STATUS_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary w-100">Simpan Status</button>
                            <div class="form-text">Selesai dan Dibatalkan bersifat final.</div>
                        </form>
                    </div>
                </div>
            @endif

            <a href="{{ route($routes.'.catalog.orders.index', $page) }}" class="btn btn-light w-100"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
    </div>
@endsection
