@extends('layouts.dashboard')

@section('title', $batch->name)
@section('page-section', 'Invoice')

@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))

@section('content')
    @include('invoice._tabs')

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h5 class="mb-0">{{ $batch->name }} @if ($batch->isCancelled()) <span class="badge bg-danger-subtle text-danger">Dibatalkan</span> @endif</h5>
            <div class="text-muted small">{{ $batch->category?->name }} · Jatuh tempo {{ $batch->due_date->translatedFormat('d F Y') }} · {{ $rp($batch->amount) }} · {{ $batch->use_late_fee ? $batch->category?->lateFeeSummary() : 'Tanpa denda' }}</div>
        </div>
        <a href="{{ route('invoices.batches.index') }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i> Semua periode</a>
    </div>

    <div class="row g-3 mb-3">
        @foreach (['paid' => ['Lunas', 'success', 'paid'], 'unpaid' => ['Belum Bayar', 'warning', 'amount'], 'cancelled' => ['Dibatalkan', 'secondary', 'amount']] as $status => [$label, $color, $field])
            <div class="col-md-4">
                <div class="card mb-0 h-100"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold text-{{ $color }}">{{ (int) ($summary[$status]->total ?? 0) }} invoice</div>
                    <div class="small">{{ $rp($summary[$status]->{$field} ?? 0) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md"><input type="text" name="search" class="form-control" placeholder="Cari customer atau nomor" value="{{ request('search') }}"></div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Invoice::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button class="btn btn-outline-secondary w-100"><i class="ri-search-line"></i> Cari</button></div>
            </form>

            @include('invoice.invoices._table', ['items' => $items, 'showBatch' => false])
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>

    @unless ($batch->isCancelled())
        <div class="row g-3 mt-0">
            <div class="col-lg-6">
                <div class="card mb-0 h-100"><div class="card-body">
                    <h6>Tambah Customer ke Periode Ini</h6>
                    @include('invoice._customer-picker', ['action' => route('invoices.batches.customers', $batch), 'button' => 'Buat Invoice'])
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-3"><div class="card-body">
                    <h6>Salin ke Periode Baru</h6>
                    <p class="small text-muted">Customer & nominal sama, cocok untuk bulan berikutnya.</p>
                    <form method="POST" action="{{ route('invoices.batches.copy', $batch) }}" class="row g-2">
                        @csrf
                        <div class="col-md-7"><input type="text" name="name" maxlength="150" class="form-control" placeholder="mis. Iuran {{ $batch->due_date->copy()->addMonthNoOverflow()->translatedFormat('F Y') }}" required></div>
                        <div class="col-md-5"><input type="date" name="due_date" class="form-control" value="{{ $batch->due_date->copy()->addMonthNoOverflow()->toDateString() }}" required></div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm">Salin Periode</button></div>
                    </form>
                </div></div>
                <div class="card mb-0"><div class="card-body">
                    <h6>Batalkan Periode</h6>
                    <p class="small text-muted">Semua invoice yang belum dibayar ikut dibatalkan. Yang sudah lunas tetap tercatat.</p>
                    <form method="POST" action="{{ route('invoices.batches.cancel', $batch) }}" class="d-flex gap-2" onsubmit="return confirm('Batalkan periode ini?')">
                        @csrf
                        <input type="text" name="reason" maxlength="250" class="form-control" placeholder="Alasan" required>
                        <button class="btn btn-outline-danger text-nowrap">Batalkan</button>
                    </form>
                </div></div>
            </div>
        </div>
    @endunless
@endsection
