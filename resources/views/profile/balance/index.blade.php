@extends('layouts.dashboard')

@section('title', 'My Account')

@section('content')
    @include('profile._tabs', ['active' => 'balance'])

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100 mb-0">
                <div class="card-body d-flex flex-column">
                    <div class="text-muted small">Saldo Anda</div>
                    <div class="fs-2 fw-bold text-primary mb-3">Rp {{ number_format($balance, 0, ',', '.') }}</div>
                    <div class="d-flex gap-2 mt-auto">
                        <a href="{{ route('account.deposits.create') }}" class="btn btn-primary flex-grow-1">
                            <i class="ri-add-line"></i> Tambah Saldo
                        </a>
                        <a href="{{ route('profile.withdrawals') }}" class="btn btn-outline-primary flex-grow-1">
                            <i class="ri-bank-line"></i> Tarik Saldo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Top Up Terakhir</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @forelse ($deposits as $deposit)
                                    <tr>
                                        <td class="fw-semibold">{{ $deposit->reference_number }}</td>
                                        <td class="text-end">Rp {{ number_format($deposit->amount, 0, ',', '.') }}</td>
                                        <td><span class="badge {{ $deposit->statusBadge() }}">{{ $deposit->statusLabel() }}</span></td>
                                        <td class="text-muted text-nowrap">{{ $deposit->created_at->translatedFormat('d M Y H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('account.deposits.show', $deposit) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted">Belum pernah top up.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">10 Mutasi Terakhir</h6>
                <a href="{{ route('profile.history') }}" class="btn btn-sm btn-outline-secondary">Lihat semua history</a>
            </div>
            @include('shared.ledger-table', ['transactions' => $transactions, 'showUser' => false])
        </div>
    </div>
@endsection
