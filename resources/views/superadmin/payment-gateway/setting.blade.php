@extends('layouts.dashboard')

@section('title', 'Deposit')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'setting'])

    @php($canManage = auth()->user()->canAccess('superadmin.payment-gateway', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Duitku</h5>
            <p class="text-muted mb-4">Kredensial Sandbox dan Production disimpan terpisah, tinggal pilih mode yang aktif.</p>

            <form method="POST" action="{{ route('superadmin.payment-gateway.setting.update') }}">
                @csrf
                @method('PUT')

                <fieldset @disabled(! $canManage)>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="mode" class="form-label">Mode Aktif</label>
                        <select id="mode" name="mode" class="form-select @error('mode') is-invalid @enderror">
                            <option value="sandbox" @selected(old('mode', $setting->mode) === 'sandbox')>Sandbox (uji coba)</option>
                            <option value="production" @selected(old('mode', $setting->mode) === 'production')>Production</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="expiry_minutes" class="form-label">Batas Waktu Bayar (menit)</label>
                        <input type="number" id="expiry_minutes" name="expiry_minutes" min="5" max="1440"
                            class="form-control @error('expiry_minutes') is-invalid @enderror" value="{{ old('expiry_minutes', $setting->expiry_minutes) }}">
                        @error('expiry_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                @checked(old('is_active', $setting->is_active))>
                            <label class="form-check-label" for="is_active">Top up aktif</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="min_deposit" class="form-label">Minimal Top Up (Rp)</label>
                        <input type="number" id="min_deposit" name="min_deposit" min="1000"
                            class="form-control @error('min_deposit') is-invalid @enderror" value="{{ old('min_deposit', $setting->min_deposit) }}">
                        @error('min_deposit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="max_deposit" class="form-label">Maksimal Top Up (Rp)</label>
                        <input type="number" id="max_deposit" name="max_deposit" min="1000"
                            class="form-control @error('max_deposit') is-invalid @enderror" value="{{ old('max_deposit', $setting->max_deposit) }}">
                        @error('max_deposit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3">
                    @foreach (['sandbox' => 'Sandbox', 'production' => 'Production'] as $mode => $label)
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ $label }}</h6>
                                    <div class="mb-3">
                                        <label for="{{ $mode }}_merchant_code" class="form-label">Merchant Code</label>
                                        <input type="text" id="{{ $mode }}_merchant_code" name="{{ $mode }}_merchant_code" class="form-control"
                                            value="{{ old($mode.'_merchant_code', $setting->{$mode.'_merchant_code'}) }}" placeholder="mis. DS12345">
                                    </div>
                                    <div>
                                        <label for="{{ $mode }}_api_key" class="form-label">API Key</label>
                                        <input type="password" id="{{ $mode }}_api_key" name="{{ $mode }}_api_key" class="form-control" autocomplete="new-password"
                                            placeholder="{{ $setting->{$mode.'_api_key'} ? 'Sudah tersimpan, isi untuk mengganti' : 'Belum diisi' }}">
                                        <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    URL callback untuk didaftarkan di dashboard Duitku:
                    <code>{{ route('payment.duitku.callback') }}</code>
                </div>

                @if ($canManage)
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                @endif
                </fieldset>
            </form>
        </div>
    </div>
@endsection
