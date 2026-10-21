@extends('layouts.dashboard')

@section('title', 'Checkout Paket')

@use('App\Support\Apps')

@php
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $enough = $balance >= $package->price;
@endphp

@section('content')
    @include('profile.packages._styles')

    <div class="pb-4">
        <a href="{{ route('packages.catalog') }}" class="d-inline-block text-muted mb-3"><i class="ri-arrow-left-line"></i> Kembali ke daftar paket</a>

        @if ($branches->isEmpty())
            <div class="alert alert-info d-flex align-items-center gap-2">
                <i class="ri-store-2-line fs-5"></i>
                <div class="flex-grow-1">Buat branch dulu ya, paket diaktifkan per branch.</div>
                <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card pkg-card h-100 mb-0 {{ $package->is_trial ? 'is-trial' : '' }}">
                    <div class="pkg-head">
                        <div class="small text-muted mb-1">{{ $package->category?->name }}</div>
                        <h4 class="mb-2">{{ $package->name }} @if ($package->is_trial) <span class="badge bg-info align-middle fs-12">Gratis</span> @endif</h4>
                        <div class="pkg-price text-primary">{{ $package->price ? $rupiah($package->price) : 'Gratis' }}</div>
                        <div class="small text-muted">untuk {{ $package->duration }} hari</div>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Layanan yang dibuka</h6>
                        <div class="row g-2 mb-4">
                            @foreach ($package->apps as $key)
                                <div class="col-sm-6">
                                    <div class="d-flex gap-2 p-2 rounded-3 bg-light h-100">
                                        <i class="{{ Apps::LIST[$key]['icon'] ?? 'ri-apps-line' }} fs-4 text-primary"></i>
                                        <div>
                                            <div class="fw-semibold">{{ Apps::label($key) }}</div>
                                            <div class="small text-muted">{{ Apps::LIST[$key]['hint'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if ($features = $package->features())
                            <h6 class="mb-2">Yang Anda dapat</h6>
                            @foreach ($features as $feature)
                                <div class="pkg-feature"><i class="ri-check-line"></i><span>{{ $feature }}</span></div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <form method="POST" action="{{ route('packages.purchase', $package) }}" class="card h-100 mb-0" id="checkout"
                    onsubmit="return confirm(this.mode.value === 'voucher' ? 'Beli paket ini sebagai kode voucher? Saldo langsung dipotong.' : {{ \Illuminate\Support\Js::from($package->price ? 'Beli paket ini seharga '.$rupiah($package->price).'? Saldo langsung dipotong.' : 'Aktifkan paket ini sekarang?') }})">
                    @csrf
                    <div class="card-body d-flex flex-column">
                        <h6 class="mb-3">Mau dipakai untuk apa?</h6>
                        <div class="d-grid gap-2 mb-3">
                            <input type="radio" class="btn-check" name="mode" value="activate" id="mode-activate" @checked(old('mode', 'activate') === 'activate')>
                            <label class="pkg-option" for="mode-activate">
                                <div class="fw-semibold"><i class="ri-flashlight-line text-primary"></i> Aktifkan sekarang</div>
                                <div class="small text-muted">Langsung aktif di branch yang dipilih.</div>
                            </label>
                            @unless ($package->is_trial)
                                <input type="radio" class="btn-check" name="mode" value="voucher" id="mode-voucher" @checked(old('mode') === 'voucher')>
                                <label class="pkg-option" for="mode-voucher">
                                    <div class="fw-semibold"><i class="ri-gift-line text-primary"></i> Jadikan kode voucher</div>
                                    <div class="small text-muted">Untuk diberikan ke orang lain atau dipakai nanti.</div>
                                </label>
                            @endunless
                        </div>

                        <div id="branch-field" class="mb-3">
                            <label class="form-label" for="branch_id">Branch</label>
                            <select name="branch_id" id="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="small text-muted mt-2" id="ends-preview"></div>
                        </div>

                        <div class="border-top pt-3 mt-auto">
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Saldo Anda</span><span>{{ $rupiah($balance) }}</span></div>
                            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Harga paket</span><span>- {{ $rupiah($package->price) }}</span></div>
                            <div class="d-flex justify-content-between fw-semibold mb-3">
                                <span>Sisa saldo</span>
                                <span class="{{ $enough ? '' : 'text-danger' }}">{{ $enough ? $rupiah($balance - $package->price) : 'Kurang '.$rupiah($package->price - $balance) }}</span>
                            </div>

                            @if ($enough)
                                <button type="submit" class="btn btn-primary w-100">
                                    {{ $package->is_trial ? 'Aktifkan Trial' : 'Bayar dengan Saldo' }}
                                </button>
                            @else
                                <a href="{{ route('account.deposits.create') }}" class="btn btn-primary w-100"><i class="ri-add-line"></i> Tambah Saldo Dulu</a>
                                <div class="small text-muted text-center mt-2">Saldo Anda belum cukup untuk paket ini.</div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('checkout');
            var ends = @json($ends);
            var apps = @json(collect($package->apps)->mapWithKeys(fn ($k) => [$k => Apps::label($k)]));
            var days = {{ (int) $package->duration }};
            var fmt = function (d) { return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }); };

            // Pratinjau masa aktif: bertumpuk dari tanggal berakhir lama kalau masih aktif.
            var refresh = function () {
                var activate = form.mode.value === 'activate';
                document.getElementById('branch-field').style.display = activate ? '' : 'none';
                var current = ends[form.branch_id.value] || {};
                document.getElementById('ends-preview').innerHTML = Object.keys(apps).map(function (key) {
                    var base = current[key] && new Date(current[key]) > new Date() ? new Date(current[key]) : new Date();
                    var next = new Date(base.getTime() + days * 86400000);
                    return '<div><i class="ri-calendar-check-line"></i> ' + apps[key] + ' aktif s/d <strong>' + fmt(next) + '</strong>' + (current[key] && new Date(current[key]) > new Date() ? ' (diperpanjang)' : '') + '</div>';
                }).join('');
            };

            form.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', refresh); });
            if (form.branch_id) form.branch_id.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endpush
