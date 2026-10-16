@extends('layouts.dashboard')

@section('title', 'Payment Gateway')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.payment-gateway._tabs', ['active' => 'deposits'])

    <div class="card">
        <div class="card-body">
            @include('superadmin.payment-gateway._filters', [
                'placeholder' => 'Nama / email / no. referensi',
                'selectLabel' => 'Status',
                'selectName' => 'status',
                'selectOptions' => \App\Models\Deposit::STATUS_LABELS,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>User</th>
                            <th class="text-end">Nominal</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Dibayar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deposits as $deposit)
                            <tr>
                                <td class="fw-semibold">{{ $deposit->reference_number }}</td>
                                <td>
                                    {{ $deposit->user?->name ?? '-' }}
                                    <div class="text-muted small">{{ $deposit->user?->email }}</div>
                                </td>
                                <td class="text-end">Rp {{ number_format($deposit->amount, 0, ',', '.') }}</td>
                                <td>{{ $deposit->payment_method ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $deposit->statusBadge() }}">{{ $deposit->statusLabel() }}</span>
                                    @if ($deposit->failure_reason)
                                        <div class="text-muted small">{{ $deposit->failure_reason }}</div>
                                    @endif
                                </td>
                                <td>{{ $deposit->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $deposit->paid_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada deposit.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $deposits->links() }}</div>
        </div>
    </div>
@endsection
