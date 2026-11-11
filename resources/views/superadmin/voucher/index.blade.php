@extends('layouts.dashboard')

@section('title', 'Voucher')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <p class="text-muted mb-0">Voucher terbuat otomatis saat user membeli package, atau dibuat manual di sini (gratis). Selama belum dipakai, kodenya boleh diberikan ke orang lain.</p>
                @if (auth()->user()->canAccess('superadmin.vouchers', \App\Support\Menu::ABILITY_MANAGE))
                    <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#voucher-new"><i class="ri-coupon-3-line"></i> Buat kode voucher</button>
                @endif
            </div>

            {{-- Kode yang baru dibuat: tampil sekali setelah simpan, siap disalin & dikirim ke tempat kursus. --}}
            @if (session('codes'))
                <div class="alert alert-success">
                    <div class="fw-semibold mb-2">Kode voucher baru:</div>
                    <textarea class="form-control font-monospace mb-2" rows="{{ min(6, count(session('codes'))) }}" readonly onclick="this.select()">{{ implode("\n", session('codes')) }}</textarea>
                    <button type="button" class="btn btn-sm btn-success" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent = 'Tersalin ✓'"><i class="ri-file-copy-line"></i> Salin semua</button>
                    <span class="small ms-2">Cara pakai: login akun bizbos › Paket Saya › <b>Redeem Voucher</b> › masukkan kode & pilih cabang.</span>
                </div>
            @endif

            @if (auth()->user()->canAccess('superadmin.vouchers', \App\Support\Menu::ABILITY_MANAGE))
                <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="voucher-new">
                    <form method="POST" action="{{ route('superadmin.vouchers.store') }}" class="border rounded p-3 mb-3 bg-light-subtle row g-3" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                        @csrf
                        <div class="col-12"><span class="fw-semibold">Buat kode voucher gratis</span>
                            <span class="small text-muted">— mis. untuk tempat kursus yang butuh masa coba lebih lama. Harga Rp 0, tidak memotong saldo siapa pun.</span></div>
                        @if ($errors->any()) <div class="col-12"><div class="alert alert-danger mb-0 py-2">{{ $errors->first() }}</div></div> @endif
                        <div class="col-12 col-md-5">
                            <label class="form-label">Paket</label>
                            <select name="package_id" class="form-select" required>
                                <option value="">Pilih paket...</option>
                                @foreach ($packages as $package)
                                    <option value="{{ $package->id }}" @selected(old('package_id') === $package->id)>
                                        {{ $package->name }} · {{ $package->durationLabel() }} · {{ implode(', ', $package->appLabels()) }}{{ $package->isActive() ? '' : ' (tidak dijual)' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Isi voucher = layanan & masa aktif paket ini. Butuh durasi lain (mis. 30 hari)? Buat paket baru di Superadmin › Package, boleh dengan status tidak aktif supaya tidak tampil di katalog.</div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Jumlah kode</label>
                            <input type="number" name="count" class="form-control" min="1" max="50" required value="{{ old('count', 1) }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Kode berlaku (hari)</label>
                            <input type="number" name="valid_days" class="form-control" min="1" max="365" required value="{{ old('valid_days', 30) }}">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="note" class="form-control" maxlength="150" value="{{ old('note') }}" placeholder="mis. Kursus Musik Harmoni">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="ri-add-line"></i> Buat kode</button>
                        </div>
                    </form>
                </div>
            @endif

            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-5">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Kode / catatan / nama / email pembeli atau pemakai" value="{{ request('search') }}">
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
                                <td>
                                    {{ $voucher->package?->name ?? '-' }}
                                    @if ($voucher->note) <div class="text-muted small">{{ $voucher->note }}</div> @endif
                                </td>
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
                                <td class="text-end">{{ $voucher->price ? 'Rp '.number_format($voucher->price, 0, ',', '.') : 'Gratis' }}</td>
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
