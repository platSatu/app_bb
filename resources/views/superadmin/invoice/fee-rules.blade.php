@extends('layouts.dashboard')

@section('title', 'Biaya per Customer')
@section('page-section', 'Superadmin')

@php
    $branchLabel = fn ($b) => trim(($b?->company?->name ? $b->company->name.' — ' : '').($b?->name ?? '-'));
@endphp

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Biaya Transaksi per Customer &amp; Metode</h5>
            <p class="text-muted mb-3">
                Customer <b>tanpa aturan</b> tetap memakai <a href="{{ route('superadmin.invoices.settings') }}">Biaya Invoice</a> global + potongan QRIS.
                Customer <b>dengan aturan</b>: biaya di sini menjadi satu-satunya potongan (sudah termasuk QRIS). Perubahan berlaku untuk pembayaran berikutnya.
            </p>
            <ul class="small text-muted mb-4">
                <li><b>Ditanggung customer</b>: dipotong dari uang yang masuk ke saldo customer. Boleh berbeda per metode; metode yang tidak diatur memakai baris "Semua metode" (kalau tidak ada: tanpa biaya).</li>
                <li><b>Ditanggung pembayar</b>: ditambahkan ke total sebelum diteruskan ke Duitku, tampil sebagai "Biaya layanan". Hanya untuk "Semua metode", dan tidak bisa digabung dengan aturan per metode.</li>
            </ul>

            <form method="POST" action="{{ route('superadmin.invoices.settings.fee-rules.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-lg-4">
                    <label for="branch_id" class="form-label">Customer (Branch)</label>
                    <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                        <option value="">Pilih customer…</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id', $branchId) === $b->id)>{{ $branchLabel($b) }}</option>
                        @endforeach
                    </select>
                    @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="payment_method" class="form-label">Metode</label>
                    <select id="payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                        <option value="">Semua metode</option>
                        @foreach ($methods as $code => $m)
                            <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $m['name'] }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-2 col-md-4">
                    <label for="fee_type" class="form-label">Jenis</label>
                    <select id="fee_type" name="fee_type" class="form-select @error('fee_type') is-invalid @enderror" required>
                        @foreach (\App\Models\InvoiceFeeRule::TYPE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('fee_type', 'flat') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-4">
                    <label for="fee_value" class="form-label">Nilai</label>
                    <input type="number" id="fee_value" name="fee_value" min="0" step="0.01" class="form-control @error('fee_value') is-invalid @enderror" value="{{ old('fee_value') }}" required>
                    @error('fee_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-2 col-md-8">
                    <label for="bearer" class="form-label">Ditanggung</label>
                    <select id="bearer" name="bearer" class="form-select @error('bearer') is-invalid @enderror" required>
                        @foreach (\App\Models\InvoiceFeeRule::BEARER_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('bearer', 'merchant') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('bearer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-lg-1 col-md-4 d-grid">
                    <button class="btn btn-primary"><i class="ri-save-line"></i> Simpan</button>
                </div>
                <div class="col-12"><div class="form-text">Customer + metode yang sama akan diperbarui (tidak dobel).</div></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 520px">
                <select name="branch" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua customer</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected($branchId === $b->id)>{{ $branchLabel($b) }}</option>
                    @endforeach
                </select>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr><th>Customer</th><th>Metode</th><th>Biaya</th><th>Ditanggung</th><th>Diubah</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody class="small">
                        @forelse ($rules as $rule)
                            <tr>
                                <td>{{ $branchLabel($rule->branch) }}</td>
                                <td>{{ $rule->payment_method ? (($methods[$rule->payment_method]['name'] ?? $rule->payment_method).' ('.$rule->payment_method.')') : 'Semua metode' }}</td>
                                <td class="fw-semibold">{{ $rule->valueLabel() }}</td>
                                <td>
                                    <span class="badge {{ $rule->bearer === 'payer' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $rule->bearer === 'payer' ? 'Pembayar' : 'Customer' }}
                                    </span>
                                </td>
                                <td class="text-muted text-nowrap">{{ $rule->updated_at?->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('superadmin.invoices.settings.fee-rules.destroy', $rule) }}" onsubmit="return confirm('Hapus aturan biaya ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada aturan. Semua customer memakai Biaya Invoice global.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $rules->links() }}</div>
        </div>
    </div>
@endsection
