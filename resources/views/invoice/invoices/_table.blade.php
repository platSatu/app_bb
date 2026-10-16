@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Nomor</th>
                <th>Customer</th>
                <th>Keterangan</th>
                <th class="text-end">Tagihan</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="text-nowrap">
                        <div class="fw-semibold">{{ $item->number }}</div>
                        <span class="badge bg-light text-dark border">{{ $item->source === 'api' ? 'API' : 'Dashboard' }}</span>
                        @if ($isAdmin) <div class="text-muted small">{{ $item->branch?->company?->name }} · {{ $item->branch?->name }}</div> @endif
                    </td>
                    <td>
                        {{ $item->customer_name }}
                        <div class="text-muted small">{{ $item->customer_email ?: ($item->customer_phone ? '+'.$item->customer_phone : '') }}</div>
                    </td>
                    <td class="small">
                        {{ \Illuminate\Support\Str::limit($item->description, 60) }}
                        @if ($item->external_id) <div class="text-muted">Ref: {{ $item->external_id }}</div> @endif
                    </td>
                    <td class="text-end text-nowrap">
                        {{ $rp($item->isPaid() ? $item->paid_amount : $item->amount) }}
                        @if ($item->isPaid() && $item->fee) <div class="text-muted small">masuk {{ $rp($item->net_amount) }}</div> @endif
                    </td>
                    <td class="text-nowrap small {{ $item->isOverdue() ? 'text-danger' : '' }}">{{ $item->due_date?->translatedFormat('d M Y') ?? ($item->expires_at ? 's.d. '.$item->expires_at->translatedFormat('d M H:i') : '-') }}</td>
                    <td><span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span></td>
                    <td class="text-end text-nowrap">
                        @if (! $isAdmin && $item->isUnpaid())
                            <button type="button" class="btn btn-sm rounded-pill btn-outline-primary" data-copy="{{ $item->payUrl() }}" title="Salin link bayar"><i class="ri-link"></i> Link</button>
                        @endif
                        <a href="{{ route($routes.'.show', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('invoice.invoices._table-script')
