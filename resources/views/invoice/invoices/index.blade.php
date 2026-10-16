@extends('layouts.dashboard')

@section('title', $isAdmin ? 'List Invoice' : 'Semua Invoice')
@section('page-section', $isAdmin ? 'Superadmin' : 'Invoice')

@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))

@section('content')
    @include('invoice._tabs')

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted small">Belum dibayar ({{ $totals['unpaid_count'] }})</div>
            <div class="fs-5 fw-bold text-warning">{{ $rp($totals['unpaid']) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted small">Total dibayar customer</div>
            <div class="fs-5 fw-bold">{{ $rp($totals['paid']) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted small">Biaya layanan bizbos</div>
            <div class="fs-5 fw-bold text-muted">{{ $rp($totals['fee']) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-0 h-100"><div class="card-body">
            <div class="text-muted small">{{ $isAdmin ? 'Masuk ke saldo user' : 'Masuk ke saldo Anda' }}</div>
            <div class="fs-5 fw-bold text-success">{{ $rp($totals['net']) }}</div>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-nowrap align-items-end gap-2 overflow-auto pb-2 mb-3">
                <div class="flex-grow-1" style="min-width: 220px">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Nomor, customer, email, ref" value="{{ request('search') }}">
                </div>
                <div style="min-width: 150px">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\Invoice::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 140px">
                    <label class="form-label small text-muted mb-1">Sumber</label>
                    <select name="source" class="form-select">
                        <option value="">Semua</option>
                        <option value="dashboard" @selected(request('source') === 'dashboard')>Dashboard</option>
                        <option value="api" @selected(request('source') === 'api')>API</option>
                    </select>
                </div>
                @if ($categories->isNotEmpty())
                    <div style="min-width: 170px">
                        <label class="form-label small text-muted mb-1">Kategori</label>
                        <select name="category" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="submit" class="btn btn-primary text-nowrap"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'source', 'category', 'date_from', 'date_to']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @include('invoice.invoices._table', ['items' => $items, 'routes' => $routes])
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
