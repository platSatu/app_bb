@extends('layouts.dashboard')

@section('title', 'Data User')
@section('page-section', 'Superadmin')

@php
    $canManage = auth()->user()->canAccess('superadmin.referral', \App\Support\Menu::ABILITY_MANAGE);
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $statuses = \App\Models\ReferralCommission::STATUS_LABELS;
@endphp

@section('content')
    @include('superadmin.user._tabs', ['active' => 'superadmin.referral'])

    <div class="row g-3">
        @foreach ($statuses as $status => $label)
            <div class="col-md-4">
                <a href="{{ route('superadmin.referral.index', ['status' => $status]) }}" class="card mb-0 text-reset">
                    <div class="card-body">
                        <div class="small text-muted">Komisi {{ strtolower($label) }}</div>
                        <div class="fs-4 fw-semibold">{{ $rupiah($totals[$status]->total ?? 0) }}</div>
                        <div class="small text-muted">{{ (int) ($totals[$status]->count ?? 0) }} transaksi</div>
                    </div>
                </a>
            </div>
        @endforeach

        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h5 class="mb-1">Aturan Komisi</h5>
                    <p class="text-muted small mb-3">Pengajak dapat persen dari setiap pembelian paket orang yang diajaknya, selama masa berlaku sejak orang itu daftar. Pengajak bisa membagi sebagian persennya ke orang yang diajak sebagai potongan harga. Komisi ditahan dulu (bisa dibatalkan di bawah), lalu otomatis cair ke saldo.</p>

                    <form method="POST" action="{{ route('superadmin.referral.update') }}">
                        @csrf
                        @method('PUT')
                        <fieldset @disabled(! $canManage)>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <label for="default_percent" class="form-label">Persen default</label>
                                    <div class="input-group">
                                        <input type="number" id="default_percent" name="default_percent" min="0" max="100" class="form-control @error('default_percent') is-invalid @enderror" value="{{ old('default_percent', $setting->default_percent) }}">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @error('default_percent') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-4">
                                    <label for="hold_days" class="form-label">Ditahan</label>
                                    <div class="input-group">
                                        <input type="number" id="hold_days" name="hold_days" min="0" max="365" class="form-control @error('hold_days') is-invalid @enderror" value="{{ old('hold_days', $setting->hold_days) }}">
                                        <span class="input-group-text">hari</span>
                                    </div>
                                    @error('hold_days') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-sm-4">
                                    <label for="active_months" class="form-label">Berlaku</label>
                                    <div class="input-group">
                                        <input type="number" id="active_months" name="active_months" min="1" max="120" class="form-control @error('active_months') is-invalid @enderror" value="{{ old('active_months', $setting->active_months) }}">
                                        <span class="input-group-text">bulan</span>
                                    </div>
                                    @error('active_months') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $setting->is_active))>
                                        <label class="form-check-label" for="is_active">Program referral aktif</label>
                                    </div>
                                    @if ($canManage) <button type="submit" class="btn btn-primary">Simpan</button> @endif
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h5 class="mb-1">Persen Khusus per User</h5>
                    <p class="text-muted small mb-3">User di luar daftar ini memakai persen default ({{ $setting->default_percent }}%). Kosongkan persen untuk mengembalikan ke default.</p>

                    @if ($canManage)
                        <form method="POST" action="{{ route('superadmin.referral.rate') }}" class="d-flex flex-wrap gap-2 mb-3">
                            @csrf
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" style="width: 240px; max-width: 100%" placeholder="Email user" value="{{ old('email') }}" required>
                            <div class="input-group" style="width: 120px">
                                <input type="number" name="commission_percent" min="0" max="100" class="form-control" placeholder="{{ $setting->default_percent }}" value="{{ old('commission_percent') }}">
                                <span class="input-group-text">%</span>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </form>
                        @error('email') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @error('commission_percent') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @endif

                    <div class="table-responsive" style="max-height: 220px">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>User</th><th class="text-end">Komisi</th><th class="text-end">Dibagi ke teman</th></tr></thead>
                            <tbody>
                                @forelse ($customRates as $code)
                                    <tr>
                                        <td>{{ $code->user?->name }} <div class="small text-muted">{{ $code->user?->email }}</div></td>
                                        <td class="text-end fw-semibold">{{ $code->commission_percent }}%</td>
                                        <td class="text-end">{{ $code->sharePercent($setting) }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">Semua user memakai persen default.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
                        <input type="text" name="search" class="form-control" style="width: 260px; max-width: 100%" maxlength="100" placeholder="Nama / email pengajak atau pembeli" value="{{ request('search') }}">
                        <select name="status" class="form-select" style="width: 180px; max-width: 100%">
                            <option value="">Semua status</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                    </form>
                    @error('reason') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pengajak</th>
                                    <th>Pembeli</th>
                                    <th>Paket</th>
                                    <th class="text-end">Dibayar</th>
                                    <th class="text-end">Komisi</th>
                                    <th>Status</th>
                                    @if ($canManage) <th class="text-end">Aksi</th> @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($commissions as $commission)
                                    <tr>
                                        <td>{{ $commission->created_at->translatedFormat('d M Y H:i') }}</td>
                                        <td>{{ $commission->referrer?->name ?? '-' }}<div class="small text-muted">{{ $commission->referrer?->email }}</div></td>
                                        <td>{{ $commission->buyer?->name ?? '(akun dihapus)' }}<div class="small text-muted">{{ $commission->buyer?->email }}</div></td>
                                        <td>{{ $commission->package_name }}</td>
                                        <td class="text-end">
                                            {{ $rupiah($commission->paid_amount) }}
                                            @if ($commission->discount_amount) <div class="small text-muted">potongan {{ $rupiah($commission->discount_amount) }} ({{ $commission->share_percent }}%)</div> @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ $rupiah($commission->amount) }}<div class="small text-muted fw-normal">{{ $commission->commission_percent - $commission->share_percent }}% dari {{ $rupiah($commission->price) }}</div></td>
                                        <td>
                                            <span class="badge {{ $commission->statusBadge() }}">{{ $commission->statusLabel() }}</span>
                                            <div class="small text-muted">
                                                @if ($commission->status === \App\Models\ReferralCommission::STATUS_HELD)
                                                    cair {{ $commission->release_at->translatedFormat('d M Y H:i') }}
                                                @elseif ($commission->cancel_reason)
                                                    {{ $commission->cancel_reason }}
                                                @elseif ($commission->released_at)
                                                    {{ $commission->released_at->translatedFormat('d M Y H:i') }}
                                                @endif
                                            </div>
                                        </td>
                                        @if ($canManage)
                                            <td class="text-end">
                                                @if ($commission->status === \App\Models\ReferralCommission::STATUS_HELD)
                                                    <form method="POST" action="{{ route('superadmin.referral.cancel', $commission) }}" class="d-inline-flex gap-1"
                                                        onsubmit="return confirm('Batalkan komisi ini? Komisi tidak akan masuk ke saldo pengajak.');">
                                                        @csrf
                                                        <input type="text" name="reason" class="form-control form-control-sm" style="width: 170px" placeholder="Alasan (komplain/refund)" minlength="5" maxlength="255" required>
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada komisi referral.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $commissions->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
