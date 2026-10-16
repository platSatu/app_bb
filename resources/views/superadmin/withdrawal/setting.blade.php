@extends('layouts.dashboard')

@section('title', 'Penarikan Saldo')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.withdrawal._tabs', ['active' => 'setting'])

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                <div>
                    <h5 class="mb-1">Duitku Disbursement</h5>
                    <p class="text-muted mb-0">Produk Duitku untuk mengirim dana ke rekening, kredensialnya berbeda dari payment gateway. IP server harus didaftarkan di Duitku.</p>
                </div>
                @if ($setting->isConfigured())
                    <form method="POST" action="{{ route('superadmin.disbursement.balance') }}">
                        @csrf
                        <button class="btn btn-outline-secondary"><i class="ri-wallet-3-line me-1"></i>Cek Saldo Duitku</button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('superadmin.disbursement.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="mode" class="form-label">Mode Aktif</label>
                        <select id="mode" name="mode" class="form-select">
                            <option value="sandbox" @selected(old('mode', $setting->mode) === 'sandbox')>Sandbox (uji coba)</option>
                            <option value="production" @selected(old('mode', $setting->mode) === 'production')>Production</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="withdrawal_fee" class="form-label">Biaya Tarik Saldo (Rp)</label>
                        <input type="number" id="withdrawal_fee" name="withdrawal_fee" min="0" max="100000"
                            class="form-control @error('withdrawal_fee') is-invalid @enderror" value="{{ old('withdrawal_fee', $setting->withdrawal_fee) }}">
                        @error('withdrawal_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="min_amount" class="form-label">Minimal Tarik Saldo (Rp)</label>
                        <input type="number" id="min_amount" name="min_amount" min="10000"
                            class="form-control @error('min_amount') is-invalid @enderror" value="{{ old('min_amount', $setting->min_amount) }}">
                        @error('min_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $setting->is_active))>
                            <label class="form-check-label" for="is_active">Tarik saldo aktif</label>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    @foreach (['sandbox' => 'Sandbox', 'production' => 'Production'] as $mode => $label)
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ $label }}</h6>
                                    <div class="mb-3">
                                        <label for="{{ $mode }}_user_id" class="form-label">User ID</label>
                                        <input type="text" id="{{ $mode }}_user_id" name="{{ $mode }}_user_id" inputmode="numeric"
                                            class="form-control @error($mode.'_user_id') is-invalid @enderror" value="{{ old($mode.'_user_id', $setting->{$mode.'_user_id'}) }}">
                                        @error($mode.'_user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label for="{{ $mode }}_email" class="form-label">Email Akun Duitku</label>
                                        <input type="email" id="{{ $mode }}_email" name="{{ $mode }}_email"
                                            class="form-control @error($mode.'_email') is-invalid @enderror" value="{{ old($mode.'_email', $setting->{$mode.'_email'}) }}">
                                        @error($mode.'_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label for="{{ $mode }}_secret_key" class="form-label">Secret Key</label>
                                        <input type="password" id="{{ $mode }}_secret_key" name="{{ $mode }}_secret_key" class="form-control" autocomplete="new-password"
                                            placeholder="{{ $setting->{$mode.'_secret_key'} ? 'Sudah tersimpan, isi untuk mengganti' : 'Belum diisi' }}">
                                        <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
