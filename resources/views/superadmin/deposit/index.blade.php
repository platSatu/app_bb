@extends('layouts.dashboard')

@section('title', 'Deposit')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'deposits'])

    {{-- Ringkasan: mengikuti filter cari & tanggal di bawah (filter status hanya untuk tabel). --}}
    @php
        $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
        $from = $summary['from']?->translatedFormat('d M Y');
        $until = $summary['until']?->translatedFormat('d M Y');
        $period = match (true) {
            $from && $until => $from.' – '.$until,
            (bool) $from => 'Sejak '.$from,
            (bool) $until => 'Sampai '.$until,
            default => 'Semua waktu',
        };
        $cards = [
            ['Total Deposit Masuk', $rp($summary['top_up']['total']), 'ri-wallet-3-line', 'primary',
                $summary['top_up']['trx'].' deposit berhasil dari '.$summary['top_up']['users'].' user · '.$period],
            ['Sisa Saldo User', $rp($summary['balance']['total']), 'ri-safe-2-line', 'success',
                $summary['balance']['users'].' user masih punya saldo · '.($until ? 'per '.$until : 'saat ini')],
            ['Penjualan Paket', $rp($summary['sales']['total']), 'ri-shopping-bag-3-line', 'warning',
                $summary['sales']['trx'].' paket terjual · '.$period],
        ];
    @endphp
    <div class="row g-3 mb-3">
        @foreach ($cards as [$label, $value, $icon, $color, $note])
            <div class="col-sm-6 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center" style="width:36px;height:36px"><i class="{{ $icon }} fs-5"></i></span>
                        </div>
                        <h4 class="mb-1">{{ $value }}</h4>
                        <div class="small text-muted">{{ $note }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            @include('shared.filters', [
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
