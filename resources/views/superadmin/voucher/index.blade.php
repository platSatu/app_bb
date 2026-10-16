@extends('layouts.dashboard')

@section('title', 'Voucher')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <p class="text-muted">Voucher terbuat otomatis saat user membeli package. Selama belum dipakai, kodenya boleh diberikan ke orang lain.</p>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-5">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Kode / nama / email pembeli atau pemakai" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\Voucher::STATUS_LABELS as $value => $label)
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
                            <th>Kode</th>
                            <th>Package</th>
                            <th>Pembeli</th>
                            <th>Dipakai Oleh</th>
                            <th class="text-end">Harga</th>
                            <th>Berlaku</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vouchers as $voucher)
                            <tr>
                                <td><code>{{ $voucher->code }}</code></td>
                                <td>{{ $voucher->package?->name ?? '-' }}</td>
                                <td>
                                    {{ $voucher->owner?->name ?? '-' }}
                                    <div class="text-muted small">{{ $voucher->owner?->email }}</div>
                                </td>
                                <td>
                                    @if ($voucher->redeemer)
                                        {{ $voucher->redeemer->name }}
                                        <div class="text-muted small">{{ $voucher->redeemed_at->translatedFormat('d M Y H:i') }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">Rp {{ number_format($voucher->price, 0, ',', '.') }}</td>
                                <td class="small">
                                    {{ $voucher->valid_from->translatedFormat('d M Y') }} – {{ $voucher->valid_until->translatedFormat('d M Y') }}
                                </td>
                                <td><span class="badge {{ $voucher->statusBadge() }}">{{ $voucher->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada voucher.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $vouchers->links() }}</div>
        </div>
    </div>
@endsection
