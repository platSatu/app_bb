@extends('layouts.dashboard')

@section('title', 'Laporan '.$category->name)
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-categories'])

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="mb-1 fw-semibold">{{ $category->name }}</h6>
                <div class="text-muted small lh-sm">
                    Pembeli yang sudah lunas · Dijual {{ $category->quota }} · Terjual {{ $category->sold }} · Menunggu bayar {{ $category->reserved }} · Tersedia {{ $category->remaining() }}
                    · Pendapatan Rp {{ number_format($category->revenue(), 0, ',', '.') }}
                </div>
            </div>
            <a href="{{ route($routes.'.ticket.categories.index', $page) }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
        <div class="card-body p-3 p-md-4">
            @include('page.ticket._report-filter', [
                'tabs' => ['buyers' => 'Pembeli'],
                'statuses' => null,
                'searchPlaceholder' => 'Nomor pesanan, nama, email, atau WhatsApp',
            ])

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th>Pesanan</th><th>Pembeli</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th><th class="text-nowrap">Waktu Lunas</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $sale)
                                <tr>
                                    <td class="text-nowrap"><a href="{{ route($routes.'.ticket.orders.show', [$page, $sale->order_id]) }}" class="fw-semibold">{{ $sale->order?->reference }}</a></td>
                                    <td>
                                        <div class="fw-semibold">{{ $sale->order?->name }}</div>
                                        <div class="text-muted text-truncate" style="max-width: 260px;">{{ $sale->order?->email }}</div>
                                        <div class="text-muted">{{ $sale->order?->phone }}</div>
                                    </td>
                                    <td class="text-center">{{ $sale->quantity }}</td>
                                    <td class="text-end text-nowrap">{{ $sale->subtotal > 0 ? 'Rp '.number_format($sale->subtotal, 0, ',', '.') : 'Gratis' }}</td>
                                    <td class="text-nowrap">{{ $sale->order?->paid_at?->translatedFormat('d M Y') }}<div class="text-muted">{{ $sale->order?->paid_at?->format('H:i') }}</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pembeli untuk filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
@endsection
