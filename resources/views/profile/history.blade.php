@extends('layouts.dashboard')

@section('title', 'My Account')

@section('content')
    @include('profile._tabs', ['active' => 'history'])

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Uang Masuk</div>
                <div class="fs-4 fw-bold text-success">+Rp {{ number_format($totalIn, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Uang Keluar</div>
                <div class="fs-4 fw-bold text-danger">-Rp {{ number_format($totalOut, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Selisih</div>
                <div class="fs-4 fw-bold">Rp {{ number_format($totalIn - $totalOut, 0, ',', '.') }}</div>
                <div class="text-muted small">{{ request()->hasAny(['date_from', 'date_to', 'type', 'search']) ? 'Sesuai filter yang dipilih' : 'Sejak awal akun' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('shared.filters', [
                'placeholder' => 'Cari keterangan, mis. nomor order',
                'selectLabel' => 'Jenis',
                'selectName' => 'type',
                'selectOptions' => \App\Models\WalletTransaction::TYPE_LABELS,
            ])

            @include('shared.ledger-table', ['transactions' => $transactions, 'showUser' => false])

            <div class="mt-3">{{ $transactions->links() }}</div>
        </div>
    </div>
@endsection
