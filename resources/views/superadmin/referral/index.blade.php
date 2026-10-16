@extends('layouts.dashboard')

@section('title', 'Data User')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.user._tabs', ['active' => 'superadmin.referral'])

    @php($canManage = auth()->user()->canAccess('superadmin.referral', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Bonus Referral</h5>
            <p class="text-muted mb-4">Bonus masuk ke saldo pengajak saat orang yang diajak berhasil top up pertama kali (minimal sesuai nominal di bawah). Tiap orang yang diajak hanya memberi bonus satu kali.</p>

            <form method="POST" action="{{ route('superadmin.referral.update') }}">
                @csrf
                @method('PUT')

                <fieldset @disabled(! $canManage)>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="reward_amount" class="form-label">Nominal Bonus (Rp)</label>
                            <input type="number" id="reward_amount" name="reward_amount" min="0"
                                class="form-control @error('reward_amount') is-invalid @enderror" value="{{ old('reward_amount', $setting->reward_amount) }}">
                            @error('reward_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="min_deposit_amount" class="form-label">Minimal Top Up (Rp)</label>
                            <input type="number" id="min_deposit_amount" name="min_deposit_amount" min="0"
                                class="form-control @error('min_deposit_amount') is-invalid @enderror" value="{{ old('min_deposit_amount', $setting->min_deposit_amount) }}">
                            @error('min_deposit_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                                    @checked(old('is_active', $setting->is_active))>
                                <label class="form-check-label" for="is_active">Bonus referral aktif</label>
                            </div>
                        </div>
                    </div>

                    @if ($canManage)
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    @endif
                </fieldset>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-5">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Nama / email / kode" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\Referral::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Pengajak</th>
                            <th>Yang Diajak</th>
                            <th>Kode</th>
                            <th>Status</th>
                            <th class="text-end">Bonus</th>
                            <th>Daftar</th>
                            <th>Cair</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($referrals as $referral)
                            <tr>
                                <td>
                                    {{ $referral->referrer?->name ?? '-' }}
                                    <div class="text-muted small">{{ $referral->referrer?->email }}</div>
                                </td>
                                <td>
                                    {{ $referral->referredUser?->name ?? '-' }}
                                    <div class="text-muted small">{{ $referral->referredUser?->email }}</div>
                                </td>
                                <td><code>{{ $referral->code }}</code></td>
                                <td>
                                    <span class="badge {{ $referral->status === \App\Models\Referral::STATUS_REWARDED ? 'bg-success' : 'bg-secondary' }}">
                                        {{ \App\Models\Referral::STATUS_LABELS[$referral->status] ?? $referral->status }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $referral->reward_amount ? 'Rp '.number_format($referral->reward_amount, 0, ',', '.') : '-' }}</td>
                                <td>{{ $referral->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $referral->rewarded_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada referral.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $referrals->links() }}</div>
        </div>
    </div>
@endsection
