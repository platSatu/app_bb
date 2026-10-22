@extends('layouts.dashboard')

@section('title', 'Rekening Penerima')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice.disbursements._tabs')

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-0">
                <div class="card-body">
                    <h6 class="mb-1">Tambah Rekening</h6>
                    <p class="small text-muted">Nama pemilik diambil langsung dari bank, jadi pastikan nomor rekeningnya benar.</p>
                    @if ($banksError)
                        <div class="alert alert-warning small mb-0">{{ $banksError }}</div>
                    @elseif ($available)
                        <form method="POST" action="{{ route('invoices.disbursements.recipients.store') }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <label class="form-label small mb-1">Bank</label>
                            <select name="bank_code" class="form-select mb-2" required>
                                <option value="">Pilih bank</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank['bankCode'] }}" @selected(old('bank_code') === $bank['bankCode'])>{{ $bank['bankName'] }}</option>
                                @endforeach
                            </select>
                            <label class="form-label small mb-1">Nomor rekening</label>
                            <input type="text" name="account_number" inputmode="numeric" class="form-control mb-2" maxlength="30" required value="{{ old('account_number') }}">
                            <label class="form-label small mb-1">Label <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="label" class="form-control mb-3" maxlength="150" value="{{ old('label') }}" placeholder="mis. Pengajar Andi / Cabang Bekasi">
                            <button type="submit" class="btn btn-primary w-100">Cek & Simpan Rekening</button>
                        </form>
                    @else
                        <div class="small text-muted">Menunggu disbursement diaktifkan tim bizbos.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <form method="GET" class="d-flex gap-2 mb-3">
                        <input type="hidden" name="branch" value="{{ $branch->id }}">
                        <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama / label / external ID" value="{{ request('search') }}">
                        <button class="btn btn-outline-primary"><i class="ri-search-line"></i></button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light"><tr><th>Penerima</th><th>Rekening</th><th>Sumber</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($recipients as $recipient)
                                    <tr>
                                        <td>
                                            {{ $recipient->account_name }}
                                            <div class="small text-muted">{{ $recipient->label ?: '-' }}</div>
                                        </td>
                                        <td>{{ $recipient->masked() }}</td>
                                        <td class="small">{{ $recipient->source === 'api' ? 'API' : 'Dashboard' }}@if ($recipient->external_id)<div class="text-muted">{{ $recipient->external_id }}</div>@endif</td>
                                        <td><span class="badge {{ $recipient->isActive() ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $recipient->isActive() ? 'Aktif' : 'Nonaktif' }}</span></td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('invoices.disbursements.recipients.toggle', $recipient) }}"
                                                @if ($recipient->isActive()) onsubmit="return confirm('Nonaktifkan rekening ini? Rekening tidak bisa menerima dana sampai diaktifkan lagi.')" @endif>
                                                @csrf
                                                <button class="btn btn-sm {{ $recipient->isActive() ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $recipient->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada rekening penerima.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $recipients->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
