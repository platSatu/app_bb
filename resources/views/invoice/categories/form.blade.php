@extends('layouts.dashboard')

@section('title', $item->exists ? 'Edit Kategori' : 'Tambah Kategori')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ $item->exists ? 'Edit Kategori '.$item->name : 'Tambah Kategori Invoice' }}</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ $item->exists ? route('invoices.categories.update', $item) : route('invoices.categories.store') }}">
                @csrf
                @if ($item->exists) @method('PUT') @endif

                <div class="row g-3">
                    @if (! $item->exists)
                        <div class="col-md-6">
                            <label for="branch_id" class="form-label">Branch</label>
                            <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama Kategori</label>
                        <input type="text" id="name" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $item->name) }}" placeholder="mis. Iuran Bulanan" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="prefix" class="form-label">Prefix Nomor</label>
                        <input type="text" id="prefix" name="prefix" maxlength="10" class="form-control text-uppercase @error('prefix') is-invalid @enderror" value="{{ old('prefix', $item->prefix) }}" required>
                        <div class="form-text">Contoh nomor: {{ old('prefix', $item->prefix ?: 'INV') }}-2610AB12CD</div>
                        @error('prefix') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            @foreach (\App\Models\InvoiceCategory::STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-1"><h6 class="mb-0">Denda Keterlambatan</h6></div>
                    <div class="col-md-6">
                        <label for="late_fee_mode" class="form-label">Jenis Denda</label>
                        <select id="late_fee_mode" name="late_fee_mode" class="form-select @error('late_fee_mode') is-invalid @enderror" data-late-mode>
                            @foreach (\App\Models\InvoiceCategory::LATE_FEE_MODES as $value => $label)
                                <option value="{{ $value }}" @selected(old('late_fee_mode', $item->late_fee_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3" data-late="percent percent_flat">
                        <label for="late_fee_percent" class="form-label">Persen (%)</label>
                        <input type="number" id="late_fee_percent" name="late_fee_percent" min="0" max="100" step="0.01" class="form-control @error('late_fee_percent') is-invalid @enderror" value="{{ old('late_fee_percent', $item->late_fee_percent) }}">
                        @error('late_fee_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3" data-late="percent">
                        <label for="late_fee_period" class="form-label">Dihitung per</label>
                        <select id="late_fee_period" name="late_fee_period" class="form-select">
                            <option value="day" @selected(old('late_fee_period', $item->late_fee_period) === 'day')>Hari</option>
                            <option value="month" @selected(old('late_fee_period', $item->late_fee_period) === 'month')>Bulan (30 hari)</option>
                        </select>
                    </div>
                    <div class="col-md-3" data-late="percent_flat">
                        <label for="late_fee_percent_days" class="form-label">Persen sampai hari ke-</label>
                        <input type="number" id="late_fee_percent_days" name="late_fee_percent_days" min="1" max="365" class="form-control @error('late_fee_percent_days') is-invalid @enderror" value="{{ old('late_fee_percent_days', $item->late_fee_percent_days) }}">
                        @error('late_fee_percent_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3" data-late="flat percent_flat">
                        <label for="late_fee_flat" class="form-label" data-flat-label>Nominal (Rp)</label>
                        <input type="number" id="late_fee_flat" name="late_fee_flat" min="0" class="form-control @error('late_fee_flat') is-invalid @enderror" value="{{ old('late_fee_flat', $item->late_fee_flat) }}">
                        @error('late_fee_flat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12"><hr class="my-1"><h6 class="mb-0">Email ke Customer</h6></div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-1">
                            <input type="hidden" name="email_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="email_enabled" name="email_enabled" value="1" @checked(old('email_enabled', $item->email_enabled))>
                            <label class="form-check-label" for="email_enabled">Kirim email tagihan & pengingat</label>
                        </div>
                        <div class="form-text">Hanya untuk customer yang punya email di Buku Kontak.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="reminder_days" class="form-label">Pengingat (hari sebelum jatuh tempo)</label>
                        <input type="text" id="reminder_days" name="reminder_days" class="form-control @error('reminder_days') is-invalid @enderror @error('reminder_days.*') is-invalid @enderror"
                            value="{{ implode(', ', (array) old('reminder_days', $item->reminder_days ?? [])) }}" placeholder="mis. 3, 1, 0">
                        <div class="form-text">Pisahkan dengan koma. 0 = di hari jatuh tempo. Maksimal 5.</div>
                        @error('reminder_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('reminder_days.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Keterangan (opsional)</label>
                        <textarea id="description" name="description" rows="2" maxlength="1000" class="form-control">{{ old('description', $item->description) }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('invoices.categories.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var mode = document.querySelector('[data-late-mode]');
            var sync = function () {
                document.querySelectorAll('[data-late]').forEach(function (el) {
                    el.classList.toggle('d-none', el.dataset.late.split(' ').indexOf(mode.value) === -1);
                });
                document.querySelector('[data-flat-label]').textContent = mode.value === 'percent_flat' ? 'Lalu per hari (Rp)' : 'Nominal (Rp)';
            };
            mode.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
