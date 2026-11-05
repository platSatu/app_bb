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
            <p class="text-muted small mb-3">
                Customer <b>tanpa aturan</b> memakai <a href="{{ route('superadmin.invoices.settings') }}">Biaya Invoice</a> global + potongan QRIS.
                Customer <b>dengan aturan</b>: biaya di sini menjadi satu-satunya potongan (sudah termasuk QRIS). Berlaku untuk pembayaran berikutnya.
            </p>
            <div class="row g-2 mb-4 small">
                <div class="col-md-6">
                    <div class="border rounded p-2 h-100 bg-light-subtle">
                        <div class="fw-semibold mb-1"><i class="ri-store-2-line me-1"></i>Ditanggung customer</div>
                        <div class="text-muted">Dipotong dari uang yang masuk ke saldo customer. Boleh beda per metode; metode yang tidak diatur memakai baris "Semua metode" (kalau tidak ada: tanpa biaya).</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-2 h-100 bg-light-subtle">
                        <div class="fw-semibold mb-1"><i class="ri-user-line me-1"></i>Ditanggung pembayar</div>
                        <div class="text-muted">Ditambahkan ke total sebelum ke Duitku, tampil sebagai "Biaya layanan". Hanya untuk "Semua metode" dan tidak bisa digabung dengan aturan per metode.</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('superadmin.invoices.settings.fee-rules.store') }}" id="fee-rule-form">
                @csrf
                <div class="row g-3">
                    <div class="col-md-7">
                        <label for="branch_id" class="form-label">Customer (Branch)</label>
                        <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                            <option value="">Pilih customer…</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" @selected(old('branch_id', $branchId) === $b->id)>{{ $branchLabel($b) }}</option>
                            @endforeach
                        </select>
                        @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label for="payment_method" class="form-label">Metode</label>
                        <select id="payment_method" name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            <option value="">Semua metode</option>
                            @foreach ($methods as $code => $m)
                                <option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $m['name'] }} ({{ $code }})</option>
                            @endforeach
                        </select>
                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text d-none" data-payer-note>Ditanggung pembayar selalu untuk semua metode.</div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <label for="fee_type" class="form-label">Jenis</label>
                        <select id="fee_type" name="fee_type" class="form-select @error('fee_type') is-invalid @enderror" required>
                            @foreach (\App\Models\InvoiceFeeRule::TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('fee_type', 'flat') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <label for="fee_value" class="form-label">Nilai</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text" data-unit-before>Rp</span>
                            <input type="number" id="fee_value" name="fee_value" min="0" step="0.01" inputmode="decimal" placeholder="0" class="form-control @error('fee_value') is-invalid @enderror" value="{{ old('fee_value') }}" required>
                            <span class="input-group-text d-none" data-unit-after>%</span>
                            @error('fee_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label for="bearer" class="form-label">Ditanggung</label>
                        <select id="bearer" name="bearer" class="form-select @error('bearer') is-invalid @enderror" required>
                            @foreach (\App\Models\InvoiceFeeRule::BEARER_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('bearer', 'merchant') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('bearer') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-3 mt-4">
                    <div class="form-text m-0">Customer + metode yang sama akan diperbarui (tidak dobel).</div>
                    <button type="submit" class="btn btn-primary px-4"><i class="ri-save-line me-1"></i>Simpan Aturan</button>
                </div>
            </form>
            <script>
                (function () {
                    var form = document.getElementById('fee-rule-form');
                    var type = form.querySelector('#fee_type'), bearer = form.querySelector('#bearer'), method = form.querySelector('#payment_method');
                    var before = form.querySelector('[data-unit-before]'), after = form.querySelector('[data-unit-after]'), note = form.querySelector('[data-payer-note]');
                    function sync() {
                        var percent = type.value === 'percent';
                        before.classList.toggle('d-none', percent);
                        after.classList.toggle('d-none', !percent);
                        // Ditanggung pembayar hanya boleh "Semua metode" (server tetap memvalidasi).
                        var payer = bearer.value === 'payer';
                        if (payer) method.value = '';
                        method.disabled = payer;
                        note.classList.toggle('d-none', !payer);
                    }
                    type.addEventListener('change', sync);
                    bearer.addEventListener('change', sync);
                    sync();
                })();
            </script>
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
