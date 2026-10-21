@extends('layouts.dashboard')

@section('title', 'Redeem Voucher')

@section('content')
    <div class="pb-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-2" style="width:56px;height:56px"><i class="ri-coupon-2-line fs-3"></i></span>
                            <h5 class="mb-1">Pakai Kode Voucher</h5>
                            <p class="text-muted small mb-0">Masukkan kode dari pembelian Anda sendiri atau pemberian orang lain.</p>
                        </div>

                        @if ($branches->isEmpty())
                            <div class="alert alert-info d-flex align-items-center gap-2 mb-0">
                                <i class="ri-store-2-line fs-5"></i>
                                <div class="flex-grow-1">Buat branch dulu ya, voucher diaktifkan per branch.</div>
                                <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
                            </div>
                        @else
                            <form method="POST" action="{{ route('packages.redeem.store') }}">
                                @csrf
                                <input type="text" name="code" id="voucher-code" value="{{ old('code') }}" maxlength="20" autocomplete="off"
                                    class="form-control form-control-lg text-center text-uppercase fw-semibold mb-3 @error('code') is-invalid @enderror" style="letter-spacing:.15em" placeholder="BZXXXXXXXXXX" required>
                                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <label class="form-label" for="branch_id">Aktifkan untuk branch</label>
                                <select name="branch_id" id="branch_id" class="form-select mb-3 @error('branch_id') is-invalid @enderror" required>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary w-100">Pakai Voucher</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($vouchers->isNotEmpty())
                    <div class="card mb-0">
                        <div class="card-body">
                            <h6 class="mb-3">Voucher Anda yang belum dipakai</h6>
                            @foreach ($vouchers as $voucher)
                                <div class="d-flex align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                    <div class="flex-grow-1">
                                        <code class="fs-6">{{ $voucher->code }}</code>
                                        <div class="small text-muted">{{ $voucher->package?->name }} · {{ $voucher->package?->duration }} hari · berlaku s/d {{ $voucher->valid_until->translatedFormat('d M Y') }}</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" data-copy="{{ $voucher->code }}" title="Salin kode"><i class="ri-file-copy-line"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-use="{{ $voucher->code }}">Pakai</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-use]').forEach(function (b) {
            b.addEventListener('click', function () { var i = document.getElementById('voucher-code'); if (i) { i.value = b.dataset.use; i.focus(); } });
        });
        document.querySelectorAll('[data-copy]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(b.dataset.copy);
                b.innerHTML = '<i class="ri-check-line text-success"></i>';
                setTimeout(function () { b.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 1200);
            });
        });
    </script>
@endpush
