@extends('layouts.dashboard')

@section('title', 'My Account')

@use('App\Support\Apps')

@php
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
@endphp

@section('content')
    @include('profile._tabs', ['active' => 'packages'])

    <div class="pb-4">
        {{-- Layanan aktif per branch --}}
        <div class="card">
            <div class="card-body">
                <h6 class="mb-1"><i class="ri-shield-check-line text-primary me-1"></i> Layanan Aktif per Branch</h6>
                <p class="small text-muted mb-3">Layanan yang habis otomatis terkunci. Membeli lagi sebelum habis akan menambah masa aktif dari tanggal berakhir yang sekarang.</p>

                @if ($branches->isEmpty())
                    <div class="alert alert-info mb-0 d-flex align-items-center gap-2">
                        <i class="ri-store-2-line fs-5"></i>
                        <div class="flex-grow-1">Buat branch dulu ya, paket diaktifkan per branch.</div>
                        <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Branch</th>
                                    @foreach (Apps::LIST as $app)
                                        <th class="text-center"><i class="{{ $app['icon'] }} me-1"></i>{{ $app['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($branches as $branch)
                                    @php($own = ($subscriptions[$branch->id] ?? collect())->keyBy('app_key'))
                                    <tr>
                                        <td class="fw-semibold">{{ $branch->name }}</td>
                                        @foreach (Apps::LIST as $key => $app)
                                            @php($sub = $own->get($key))
                                            <td class="text-center">
                                                @if ($sub && $sub->isActive())
                                                    <span class="badge {{ $sub->daysLeft() <= 7 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">Aktif · {{ $sub->daysLeft() }} hari lagi</span>
                                                    <div class="small text-muted mt-1">s/d {{ $sub->ends_at->translatedFormat('d M Y') }}</div>
                                                @elseif ($sub)
                                                    <span class="badge bg-danger-subtle text-danger">Habis</span>
                                                    <div class="small text-muted mt-1">{{ $sub->ends_at->translatedFormat('d M Y') }}</div>
                                                @else
                                                    <span class="badge bg-light text-muted">Belum aktif</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($branches->isNotEmpty())
            <div class="row g-3">
                {{-- Pakai kode voucher --}}
                <div class="col-lg-5">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <h6 class="mb-1"><i class="ri-coupon-2-line text-primary me-1"></i> Pakai Kode Voucher</h6>
                            <p class="small text-muted mb-3">Punya kode voucher dari pembelian sendiri atau pemberian orang lain? Masukkan di sini.</p>
                            <form method="POST" action="{{ route('account.packages.redeem') }}">
                                @csrf
                                <input type="text" name="code" value="{{ old('code') }}" maxlength="20" class="form-control text-uppercase mb-2 @error('code') is-invalid @enderror" placeholder="Contoh: BZ8K2M4Q9XRT" required>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <select name="branch_id" class="form-select mb-2 @error('branch_id') is-invalid @enderror" required>
                                    <option value="">Aktifkan untuk branch…</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary w-100">Pakai Voucher</button>
                            </form>

                            @if ($vouchers->isNotEmpty())
                                <hr>
                                <div class="small fw-semibold mb-2">Voucher Anda yang belum dipakai</div>
                                @foreach ($vouchers as $voucher)
                                    <div class="d-flex align-items-center gap-2 py-1">
                                        <code class="fs-6">{{ $voucher->code }}</code>
                                        <span class="small text-muted flex-grow-1">{{ $voucher->package?->name }} · berlaku s/d {{ $voucher->valid_until->translatedFormat('d M Y') }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Riwayat --}}
                <div class="col-lg-7">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="ri-history-line text-primary me-1"></i> Riwayat Paket</h6>
                            @forelse ($histories as $history)
                                <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                    <div class="flex-grow-1">
                                        <div><strong>{{ $history->appLabel() }}</strong> di {{ $history->branch?->name }} {{ $history->days >= 0 ? '+'.$history->days : $history->days }} hari</div>
                                        <div class="small text-muted">{{ $history->sourceLabel() }}{{ $history->package ? ' · '.$history->package->name : '' }} · aktif s/d {{ $history->new_ends_at->translatedFormat('d M Y') }}</div>
                                    </div>
                                    <span class="small text-muted text-nowrap">{{ $history->created_at->translatedFormat('d M Y') }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted small py-4">Belum ada riwayat paket.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Daftar paket --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4 mb-2">
                <h6 class="mb-0"><i class="ri-shopping-bag-3-line text-primary me-1"></i> Beli Paket</h6>
                <div class="small">Saldo Anda <strong>{{ $rupiah($balance) }}</strong> · <a href="{{ route('account.deposits.create') }}">Tambah saldo</a></div>
            </div>

            @forelse ($packages as $group => $items)
                <div class="text-muted small fw-semibold text-uppercase mt-3 mb-2">{{ $group }}</div>
                <div class="row g-3">
                    @foreach ($items as $package)
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 mb-0 {{ $package->is_trial ? 'border-info' : '' }}">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <h6 class="mb-1">{{ $package->name }}</h6>
                                        @if ($package->is_trial) <span class="badge bg-info">Trial gratis</span> @endif
                                    </div>
                                    <div class="fs-4 fw-bold text-primary">{{ $package->price ? $rupiah($package->price) : 'Gratis' }}</div>
                                    <div class="small text-muted mb-2">{{ $package->duration }} hari</div>
                                    <div class="mb-2">
                                        @foreach ($package->appLabels() as $label)
                                            <span class="badge bg-primary-subtle text-primary">{{ $label }}</span>
                                        @endforeach
                                    </div>
                                    @if ($features = $package->features())
                                        <ul class="small ps-3 mb-3">
                                            @foreach ($features as $feature) <li>{{ $feature }}</li> @endforeach
                                        </ul>
                                    @endif

                                    <form method="POST" action="{{ route('account.packages.purchase', $package) }}" class="mt-auto" data-buy
                                        data-confirm="{{ $package->price ? 'Beli '.$package->name.' seharga '.$rupiah($package->price).'? Saldo akan langsung dipotong.' : 'Aktifkan '.$package->name.'?' }}">
                                        @csrf
                                        <select name="branch_id" class="form-select form-select-sm mb-2" data-branch>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}">Aktifkan di {{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="mode" value="activate">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1" @disabled($package->price > $balance)>
                                                {{ $package->is_trial ? 'Coba Gratis' : 'Beli & Aktifkan' }}
                                            </button>
                                            @unless ($package->is_trial)
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" data-as-voucher title="Simpan sebagai kode voucher untuk diberikan ke orang lain" @disabled($package->price > $balance)>
                                                    <i class="ri-gift-line"></i> Jadikan Voucher
                                                </button>
                                            @endunless
                                        </div>
                                        @if ($package->price > $balance)
                                            <div class="small text-danger mt-1">Saldo kurang {{ $rupiah($package->price - $balance) }}.</div>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-muted">Belum ada paket yang dijual.</div></div>
            @endforelse
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('form[data-buy]').forEach(function (form) {
            var asVoucher = false;
            var voucherButton = form.querySelector('[data-as-voucher]');
            if (voucherButton) voucherButton.addEventListener('click', function () { asVoucher = true; });
            form.addEventListener('submit', function (event) {
                form.querySelector('input[name=mode]').value = asVoucher ? 'voucher' : 'activate';
                var message = asVoucher ? form.dataset.confirm + ' Paket disimpan sebagai kode voucher.' : form.dataset.confirm;
                if (!confirm(message)) { event.preventDefault(); asVoucher = false; }
            });
        });
    </script>
@endpush
