@extends('layouts.dashboard')

@section('title', 'Customer · '.$category->name)
@section('page-section', 'Invoice')

@section('content')
    @include('invoice._tabs')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h5 class="mb-0">{{ $category->name }}</h5>
            <div class="text-muted small">{{ $category->branch?->name }} · {{ $subscriptions->where('status', 'active')->count() }} customer aktif</div>
        </div>
        <a href="{{ route('invoices.batches.create', ['category' => $category->id]) }}" class="btn btn-success"><i class="ri-add-line"></i> Buat Invoice Periode</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6>Pilih dari Buku Kontak</h6>
                    @include('invoice._customer-picker', ['action' => route('invoices.categories.subscribe', $category), 'button' => 'Tambahkan ke Kategori'])
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6>Customer Baru</h6>
                    <form method="POST" action="{{ route('invoices.categories.quick-add', $category) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <input type="text" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror" placeholder="Nama" value="{{ old('name') }}" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <input type="tel" name="phone" maxlength="20" class="form-control @error('phone') is-invalid @enderror" placeholder="WhatsApp" value="{{ old('phone') }}">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <input type="email" name="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" placeholder="Email" value="{{ old('email') }}">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <input type="number" name="amount" min="0" class="form-control @error('amount') is-invalid @enderror" placeholder="Nominal khusus (opsional)" value="{{ old('amount') }}">
                            <div class="form-text">Kosongkan untuk memakai nominal periode.</div>
                        </div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm">Simpan Customer</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Customer di Kategori Ini</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Customer</th><th>Kontak</th><th style="width: 330px">Nominal Khusus & Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr>
                                <td class="fw-semibold">{{ $subscription->customer?->displayName() }}</td>
                                <td class="small">{{ $subscription->customer?->phone ? '+'.$subscription->customer->phone : '' }}<div class="text-muted">{{ $subscription->customer?->email ?: 'Tanpa email' }}</div></td>
                                <td>
                                    <form method="POST" action="{{ route('invoices.categories.subscriptions.update', [$category, $subscription]) }}" class="d-flex gap-1">
                                        @csrf
                                        @method('PUT')
                                        <input type="number" name="amount" min="0" class="form-control form-control-sm" placeholder="Ikut periode" value="{{ $subscription->amount }}">
                                        <select name="status" class="form-select form-select-sm" style="width: 120px">
                                            @foreach (\App\Models\InvoiceSubscription::STATUS_LABELS as $value => $label)
                                                <option value="{{ $value }}" @selected($subscription->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-secondary" title="Simpan"><i class="ri-save-line"></i></button>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('invoices.categories.subscriptions.destroy', [$category, $subscription]) }}" onsubmit="return confirm('Keluarkan customer ini dari kategori?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-user-unfollow-line"></i> Keluarkan</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada customer. Pilih dari Buku Kontak atau tambahkan customer baru di atas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
