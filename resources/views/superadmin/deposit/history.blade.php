@extends('layouts.dashboard')

@section('title', 'Deposit')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'history'])

    <div class="card">
        <div class="card-body">
            @if (auth()->user()->canAccess('superadmin.wallet-adjustment', \App\Support\Menu::ABILITY_MANAGE))
                <div class="d-flex justify-content-end mb-2">
                    <a href="{{ route('superadmin.wallet-adjustment.create', array_filter(['email' => request('search')])) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-scales-3-line me-1"></i>Koreksi Saldo
                    </a>
                </div>
            @endif

            @include('shared.filters', [
                'placeholder' => 'Nama / email user / keterangan',
                'selectLabel' => 'Sumber',
                'selectName' => 'type',
                'selectOptions' => \App\Models\WalletTransaction::TYPE_LABELS,
            ])

            @include('shared.ledger-table', ['transactions' => $transactions, 'showUser' => true])

            <div class="mt-3">{{ $transactions->links() }}</div>
        </div>
    </div>
@endsection
