@extends('layouts.dashboard')

@section('title', 'Biaya Invoice')
@section('page-section', 'Superadmin')

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Biaya Layanan Invoice</h5>
            <p class="text-muted">Dipotong dari setiap pembayaran invoice sebelum masuk ke saldo user. Biaya Duitku ditanggung bizbos dari biaya ini. Perubahan berlaku untuk pembayaran berikutnya.</p>

            <form method="POST" action="{{ route('superadmin.invoices.settings.update') }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fee_percent" class="form-label">Biaya Persen (%)</label>
                        <input type="number" id="fee_percent" name="fee_percent" min="0" max="50" step="0.01" class="form-control @error('fee_percent') is-invalid @enderror" value="{{ old('fee_percent', $setting->fee_percent) }}" required data-fee="percent">
                        @error('fee_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="fee_flat" class="form-label">Biaya Flat (Rp)</label>
                        <input type="number" id="fee_flat" name="fee_flat" min="0" class="form-control @error('fee_flat') is-invalid @enderror" value="{{ old('fee_flat', $setting->fee_flat) }}" required data-fee="flat">
                        @error('fee_flat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contoh</label>
                        <div class="bg-light rounded p-2 small" data-fee="example"></div>
                    </div>
                    <div class="col-md-4">
                        <label for="min_amount" class="form-label">Nominal Minimal (Rp)</label>
                        <input type="number" id="min_amount" name="min_amount" min="10000" class="form-control @error('min_amount') is-invalid @enderror" value="{{ old('min_amount', $setting->min_amount) }}" required>
                        @error('min_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="max_amount" class="form-label">Nominal Maksimal (Rp)</label>
                        <input type="number" id="max_amount" name="max_amount" class="form-control @error('max_amount') is-invalid @enderror" value="{{ old('max_amount', $setting->max_amount) }}" required>
                        @error('max_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="api_expiry_minutes" class="form-label">Masa Berlaku Invoice API (menit)</label>
                        <input type="number" id="api_expiry_minutes" name="api_expiry_minutes" min="5" max="43200" class="form-control @error('api_expiry_minutes') is-invalid @enderror" value="{{ old('api_expiry_minutes', $setting->api_expiry_minutes) }}" required>
                        <div class="form-text">Default kalau website tidak mengirim expiry_minutes.</div>
                        @error('api_expiry_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4"><button class="btn btn-primary">Simpan</button></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var get = function (k) { return Number(document.querySelector('[data-fee=' + k + ']').value) || 0; };
            var rp = function (v) { return 'Rp ' + Math.round(v).toLocaleString('id-ID'); };
            var update = function () {
                var total = 100000, fee = Math.min(total, Math.ceil(total * get('percent') / 100 + get('flat')));
                document.querySelector('[data-fee=example]').innerHTML = 'Customer bayar ' + rp(total) + ' → biaya ' + rp(fee) + ' → masuk saldo user <strong>' + rp(total - fee) + '</strong>';
            };
            document.querySelectorAll('[data-fee=percent],[data-fee=flat]').forEach(function (el) { el.addEventListener('input', update); });
            update();
        })();
    </script>
@endpush
