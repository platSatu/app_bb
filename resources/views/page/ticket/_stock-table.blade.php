{{-- Riwayat stok. $rows = paginator TicketStockLog, $showCategory = bool. --}}
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0 small">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap">Waktu</th>
                @if ($showCategory) <th>Kategori</th> @endif
                <th>Kejadian</th>
                <th class="text-nowrap">Perubahan</th>
                <th class="text-center text-nowrap">Dijual · Ditahan · Terjual</th>
                <th>Pesanan / Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->translatedFormat('d M Y') }}<div class="text-muted">{{ $log->created_at->format('H:i:s') }}</div></td>
                    @if ($showCategory) <td>{{ $log->category?->name ?? '-' }}</td> @endif
                    <td>
                        {{ $log->actionLabel() }}
                        @if ($log->note) <div class="text-muted text-truncate" style="max-width: 220px;" title="{{ $log->note }}">{{ $log->note }}</div> @endif
                    </td>
                    <td class="text-nowrap fw-semibold">{{ $log->changes() }}</td>
                    <td class="text-center text-nowrap">{{ $log->quota_after }} · {{ $log->reserved_after }} · {{ $log->sold_after }}</td>
                    <td class="text-nowrap">
                        @if ($log->order)
                            <a href="{{ route($routes.'.ticket.orders.show', [$page, $log->order]) }}">{{ $log->order->reference }}</a>
                        @else
                            {{ $log->actor?->name ?? '-' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $showCategory ? 6 : 5 }}" class="text-center text-muted py-4">Belum ada riwayat stok untuk filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
