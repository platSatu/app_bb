@extends('layouts.dashboard')

@section('title', 'Rekening Penerima')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice.disbursements._tabs')

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-1">Tambah Rekening Penerima</h6>
            <p class="small text-muted mb-3">Pilih bank dan ketik nomor rekeningnya. Kami cek langsung ke bank, jadi nama pemilik terisi otomatis dan dana tidak salah kirim.</p>
            @if ($banksError)
                <div class="alert alert-warning small mb-0">{{ $banksError }}</div>
            @elseif ($available)
                <form method="POST" action="{{ route('invoices.disbursements.recipients.store') }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Bank</label>
                            <select name="bank_code" class="form-select" required>
                                <option value="">Pilih bank</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank['bankCode'] }}" @selected(old('bank_code') === $bank['bankCode'])>{{ $bank['bankName'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Nomor rekening</label>
                            <input type="text" name="account_number" inputmode="numeric" class="form-control" maxlength="30" required value="{{ old('account_number') }}" placeholder="Hanya angka">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Label <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="label" class="form-control" maxlength="150" value="{{ old('label') }}" placeholder="mis. Pengajar Andi">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Cek & Simpan</button>
                        </div>
                    </div>
                </form>
            @else
                <div class="small text-muted">Fitur ini sedang disiapkan tim bizbos. Rekening bisa ditambahkan begitu sudah aktif.</div>
            @endif
        </div>
    </div>

    <div class="card mb-0">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h6 class="mb-0">Daftar Rekening Penerima</h6>
                    <div class="small text-muted">Rekening dari website Anda (lewat API) juga muncul di sini.</div>
                </div>
                <form method="GET" class="d-flex gap-2" style="min-width:280px">
                    <input type="hidden" name="branch" value="{{ $branch->id }}">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, label, atau ID" value="{{ request('search') }}">
                    <button class="btn btn-outline-primary" aria-label="Cari"><i class="ri-search-line"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Pemilik Rekening</th><th>Bank & Nomor</th><th>Ditambahkan Dari</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($recipients as $recipient)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $recipient->account_name }}</div>
                                    @if ($recipient->label)<div class="small text-muted">{{ $recipient->label }}</div>@endif
                                </td>
                                <td class="text-nowrap">{{ $recipient->masked() }}</td>
                                <td class="small">
                                    {{ $recipient->source === 'api' ? 'Website (API)' : 'Dashboard' }}
                                    @if ($recipient->external_id)<div class="text-muted text-break" style="max-width:220px">{{ $recipient->external_id }}</div>@endif
                                </td>
                                <td><span class="badge {{ $recipient->isActive() ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $recipient->isActive() ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('invoices.disbursements.recipients.toggle', $recipient) }}"
                                        @if ($recipient->isActive()) onsubmit="return confirm('Nonaktifkan rekening ini? Selama nonaktif, rekening ini tidak bisa menerima dana.')" @endif>
                                        @csrf
                                        <button class="btn btn-sm {{ $recipient->isActive() ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $recipient->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada rekening penerima. Tambahkan lewat form di atas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $recipients->links() }}</div>
        </div>
    </div>
@endsection
