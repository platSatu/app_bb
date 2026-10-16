@extends('layouts.dashboard')

@section('title', 'Penarikan Saldo')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.withdrawal._tabs', ['active' => 'accounts'])

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-5 col-xl-4">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Nama user / email / nama rekening" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\BankAccount::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <p class="small text-muted">Nama rekening diambil langsung dari bank lewat Duitku. Setujui hanya jika sesuai dengan nama pemilik akun.</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Rekening</th>
                            <th>Nama di Bank</th>
                            <th>Status</th>
                            <th>Diajukan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr>
                                <td>
                                    {{ $account->user?->name ?? '-' }}
                                    <div class="text-muted small">{{ $account->user?->email }}</div>
                                </td>
                                <td>{{ $account->masked() }}</td>
                                <td class="fw-semibold">{{ $account->account_name }}</td>
                                <td>
                                    <span class="badge {{ $account->status === \App\Models\BankAccount::STATUS_APPROVED ? 'bg-success-subtle text-success' : ($account->status === \App\Models\BankAccount::STATUS_PENDING ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary') }}">{{ $account->statusLabel() }}</span>
                                    @if ($account->review_note)
                                        <div class="text-muted small text-wrap">{{ $account->review_note }}</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $account->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($account->status === \App\Models\BankAccount::STATUS_PENDING)
                                        <form method="POST" action="{{ route('superadmin.bank-accounts.review', $account) }}" class="d-flex gap-1 justify-content-end">
                                            @csrf
                                            <input type="text" name="note" class="form-control form-control-sm" style="width: 200px" maxlength="500" placeholder="Alasan (wajib jika ditolak)">
                                            <button name="approve" value="1" class="btn btn-sm btn-success">Setujui</button>
                                            <button name="approve" value="0" class="btn btn-sm btn-outline-danger">Tolak</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada rekening yang diajukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $accounts->links() }}</div>
        </div>
    </div>
@endsection
