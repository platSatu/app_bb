@extends('layouts.dashboard')

@section('title', 'Laporan Tiket')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-reports'])

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3">
            <h6 class="mb-1 fw-semibold">Laporan</h6>
            <div class="text-muted small lh-sm">Catatan asli dari sistem untuk mengecek pembayaran, email e-ticket, dan pergerakan stok. Data ini tidak bisa diubah.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            @include('page.ticket._report-filter', [
                'tabs' => ['payments' => 'Pembayaran (Duitku)', 'emails' => 'Email E-ticket', 'stock' => 'Riwayat Stok'],
                'statuses' => match ($filters['tab']) {
                    'emails' => \App\Models\TicketEmailLog::STATUS_LABELS,
                    'stock' => \App\Models\TicketStockLog::ACTION_LABELS,
                    default => \App\Models\PaymentCallback::RESULT_LABELS,
                },
                'categoryOptions' => $filters['tab'] === 'stock' ? $categories : null,
                'searchPlaceholder' => match ($filters['tab']) {
                    'emails' => 'Nomor pesanan atau email',
                    'stock' => null,
                    default => 'Nomor pesanan',
                },
            ])

            @switch($filters['tab'])
                @case('stock')
                    @include('page.ticket._stock-table', ['showCategory' => true])
                    @break

                @case('emails')
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="text-nowrap">Waktu</th><th>Pesanan</th><th>Tujuan</th><th>Pemicu</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at->translatedFormat('d M Y') }}<div class="text-muted">{{ $log->created_at->format('H:i:s') }}</div></td>
                                        <td class="text-nowrap">
                                            @if ($log->order) <a href="{{ route($routes.'.ticket.orders.show', [$page, $log->order]) }}">{{ $log->order->reference }}</a> @else - @endif
                                        </td>
                                        <td><div class="text-truncate" style="max-width: 240px;" title="{{ $log->email }}">{{ $log->email }}</div></td>
                                        <td>{{ $log->triggerLabel() }}@if ($log->actor) <div class="text-muted">oleh {{ $log->actor->name }}</div> @endif</td>
                                        <td>
                                            <span class="badge {{ $log->statusBadge() }}">{{ $log->statusLabel() }}</span>
                                            @if ($log->sent_at) <div class="text-muted">{{ $log->sent_at->format('d M H:i:s') }}</div> @endif
                                            @if ($log->error) <div class="text-danger text-truncate" style="max-width: 260px;" title="{{ $log->error }}">{{ $log->error }}</div> @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada email untuk filter ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @break

                @default
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th class="text-nowrap">Waktu</th><th>Pesanan</th><th class="text-end">Nominal</th><th>Metode</th><th>Hasil</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $log)
                                    @php($order = $orders->get($log->merchant_order_id))
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at->translatedFormat('d M Y') }}<div class="text-muted">{{ $log->created_at->format('H:i:s') }}</div></td>
                                        <td class="text-nowrap">
                                            @if ($order) <a href="{{ route($routes.'.ticket.orders.show', [$page, $order]) }}">{{ $log->merchant_order_id }}</a> @else {{ $log->merchant_order_id }} @endif
                                            <div class="text-muted">Ref Duitku: {{ $log->payload['reference'] ?? '-' }}</div>
                                        </td>
                                        <td class="text-end text-nowrap">Rp {{ number_format($log->amount(), 0, ',', '.') }}</td>
                                        <td class="text-nowrap">{{ $log->payload['paymentCode'] ?? '-' }}<div class="text-muted">Kode {{ $log->payload['resultCode'] ?? '-' }}</div></td>
                                        <td>
                                            <span class="badge {{ $log->resultBadge() }}">{{ $log->resultLabel() }}</span>
                                            @if ($log->message) <div class="text-muted text-truncate" style="max-width: 260px;" title="{{ $log->message }}">{{ $log->message }}</div> @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada callback Duitku untuk filter ini. Tiket gratis tidak melalui Duitku.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
            @endswitch
            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
@endsection
