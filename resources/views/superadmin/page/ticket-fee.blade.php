@extends('layouts.dashboard')

@section('title', 'Biaya Layanan Tiket')
@section('page-section', 'Superadmin')

@php($rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.'))

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="card mb-0"><div class="card-body"><div class="small text-muted">Penjualan tiket berbayar (lunas)</div><div class="fs-4 fw-semibold">{{ $rp($sales) }}</div></div></div></div>
        <div class="col-md-6"><div class="card mb-0"><div class="card-body"><div class="small text-muted">Biaya layanan diterima bizbos</div><div class="fs-4 fw-semibold text-success">{{ $rp($collected) }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Biaya Layanan Tiket</h5>
            <p class="text-muted">Persen dari harga tiket berbayar di setiap pesanan, dibulatkan ke atas sampai rupiah. Penyelenggara memilih sendiri apakah biaya ini dipotong dari penjualannya atau ditambahkan ke pembeli. Perubahan hanya berlaku untuk pesanan berikutnya.</p>

            <form method="POST" action="{{ route('superadmin.ticket-fee.update') }}">
                @csrf
                @method('PUT')
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="fee_percent" class="form-label">Biaya Layanan (%)</label>
                        <input type="number" id="fee_percent" name="fee_percent" min="0" max="{{ \App\Models\TicketSetting::MAX_PERCENT }}" step="0.01" class="form-control @error('fee_percent') is-invalid @enderror" value="{{ old('fee_percent', (float) $setting->fee_percent) }}" required>
                        @error('fee_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-9">
                        <div class="bg-light rounded p-2 small" id="fee-example"></div>
                    </div>
                </div>

                <h6 class="mt-4 mb-1">Tiket Gratis</h6>
                <p class="text-muted small">Penyelenggara membayar di depan dari Saldo saat menyimpan kategori tiket gratis. Jatah gratis dihitung per event (semua kategori gratis dijumlahkan). Sisa yang tidak terpakai kembali ke Saldo saat pendaftaran ditutup. Angka dikunci per event saat pertama kali disimpan.</p>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="free_allowance" class="form-label">Jatah Gratis per Event (tiket)</label>
                        <input type="number" id="free_allowance" name="free_allowance" min="0" class="form-control @error('free_allowance') is-invalid @enderror" value="{{ old('free_allowance', $setting->free_allowance) }}" required>
                        @error('free_allowance') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="free_unit_price" class="form-label">Biaya per Tiket Gratis (Rp)</label>
                        <input type="number" id="free_unit_price" name="free_unit_price" min="0" class="form-control @error('free_unit_price') is-invalid @enderror" value="{{ old('free_unit_price', $setting->free_unit_price) }}" required>
                        @error('free_unit_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                @if (auth()->user()->canAccess('superadmin.pages', 'manage'))
                    <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary">Simpan</button></div>
                @endif
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('fee_percent');
            var rp = function (v) { return 'Rp ' + v.toLocaleString('id-ID'); };
            var update = function () {
                var bps = Math.round((Number(input.value) || 0) * 100), price = 100000;
                var fee = bps > 0 ? Math.floor((price * bps + 9999) / 10000) : 0;
                document.getElementById('fee-example').innerHTML = 'Tiket ' + rp(price) + ' → biaya layanan ' + rp(fee)
                    + '<br>Ditanggung penyelenggara: pembeli bayar ' + rp(price) + ', masuk saldo <strong>' + rp(price - fee) + '</strong>'
                    + '<br>Ditanggung pembeli: pembeli bayar ' + rp(price + fee) + ', masuk saldo <strong>' + rp(price) + '</strong>';
            };
            input.addEventListener('input', update);
            update();
        })();
    </script>
@endpush
