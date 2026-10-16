@extends('layouts.dashboard')

@section('title', 'Koreksi Saldo')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'history'])

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Koreksi Saldo Manual</h5>
            <p class="text-muted mb-4">Dipakai hanya untuk membetulkan saldo. Setiap koreksi tercatat di History Saldo beserta alasan dan nama admin, dan tidak bisa dihapus.</p>

            <form method="GET" class="row g-2 align-items-end mb-4">
                <div class="col-md-6">
                    <label for="find_email" class="form-label">Cari user (email)</label>
                    <input type="email" id="find_email" name="email" class="form-control" value="{{ $email }}" placeholder="nama@contoh.com">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-secondary w-100"><i class="ri-search-line"></i> Cek</button>
                </div>
            </form>

            @if ($email !== '' && ! $target)
                <div class="alert alert-warning">User dengan email ini tidak ditemukan.</div>
            @endif

            @if ($target)
                <div class="alert alert-light border d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $target->name }}</div>
                        <div class="text-muted small">{{ $target->email }}</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Saldo saat ini</div>
                        <div class="fs-5 fw-semibold">Rp {{ number_format($balance, 0, ',', '.') }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('superadmin.wallet-adjustment.store') }}"
                    onsubmit="this.querySelector('[type=submit]').disabled = true">
                    @csrf
                    <input type="hidden" name="token" value="{{ old('token', $token) }}">
                    <input type="hidden" name="email" value="{{ $target->email }}">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="direction" class="form-label">Jenis Koreksi</label>
                            <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror">
                                <option value="credit" @selected(old('direction') === 'credit')>Tambah saldo</option>
                                <option value="debit" @selected(old('direction') === 'debit')>Kurangi saldo</option>
                            </select>
                            @error('direction') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="amount" class="form-label">Nominal (Rp)</label>
                            <input type="number" id="amount" name="amount" min="1" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Alasan</label>
                            <input type="text" id="reason" name="reason" maxlength="255" class="form-control @error('reason') is-invalid @enderror"
                                value="{{ old('reason') }}" placeholder="mis. Top up DEP2610... masuk tapi saldo belum bertambah">
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('superadmin.deposits.history') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
