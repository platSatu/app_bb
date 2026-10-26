@extends('layouts.dashboard')

@section('title', 'Sisa Saldo User')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'deposits'])

    <div class="mb-3">
        <a href="{{ route('superadmin.deposits.index', array_filter(request()->only(['search', 'date_to']))) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke Data Deposit</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h5 class="mb-1">Sisa Saldo User</h5>
                <div class="text-muted small">User yang masih punya saldo {{ $until ? 'per '.$until->translatedFormat('d M Y') : 'saat ini' }}, saldo terbesar di atas.</div>
            </div>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-5 col-xl-4">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Nama / email user" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Saldo per tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'date_to']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($balances as $row)
                            <tr>
                                <td class="text-muted">{{ $balances->firstItem() + $loop->index }}</td>
                                <td>
                                    {{ $row->name }}
                                    <div class="text-muted small">{{ $row->email }}</div>
                                </td>
                                <td class="text-end fw-semibold">Rp {{ number_format((int) $row->balance, 0, ',', '.') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('superadmin.deposits.history', array_filter(['search' => $row->email, 'date_to' => request('date_to')])) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="ri-history-line"></i> Riwayat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada user yang masih punya saldo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $balances->links() }}</div>
        </div>
    </div>
@endsection
