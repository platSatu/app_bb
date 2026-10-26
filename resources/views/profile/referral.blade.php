@extends('layouts.dashboard')

@section('title', 'My Account')

@php
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $percent = $referralCode->commissionPercent($setting);
    $share = $referralCode->sharePercent($setting);
    $held = \App\Models\ReferralCommission::STATUS_HELD;
    $released = \App\Models\ReferralCommission::STATUS_RELEASED;
    $until = fn ($referral) => $referral->created_at->copy()->addMonths($setting->active_months);
@endphp

@section('content')
    @include('profile._tabs', ['active' => 'referral'])

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-1">Kode Referral Anda</h5>
                    <p class="text-muted mb-4">Bagikan link ini. Siapa pun yang daftar lewat link Anda tercatat sebagai ajakan Anda.
                        @if ($setting->is_active)
                            Setiap kali mereka membeli paket dalam {{ $setting->active_months }} bulan pertama, Anda dapat komisi.
                        @endif
                    </p>

                    <div class="text-center border rounded-3 py-4 mb-3 bg-light">
                        <div class="text-muted small mb-1">Kode</div>
                        <div class="fs-3 fw-bold text-primary letter-spacing-2">{{ $referralCode->code }}</div>
                    </div>

                    <label for="referral-link" class="form-label">Link Referral</label>
                    <div class="input-group" data-referral-share data-link="{{ $referralCode->link() }}">
                        <input type="text" id="referral-link" class="form-control" value="{{ $referralCode->link() }}" readonly>
                        <button type="button" class="btn btn-outline-primary" data-copy title="Salin link">
                            <i class="ri-file-copy-line"></i> Salin
                        </button>
                        <button type="button" class="btn btn-outline-primary d-none" data-share title="Bagikan link">
                            <i class="ri-share-forward-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Komisi Anda</h5>
                        <span class="badge {{ $setting->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $setting->is_active ? 'Program aktif' : 'Program sedang tidak aktif' }}</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4"><div class="border rounded-3 p-2 h-100"><div class="small text-muted">Persen komisi</div><div class="fs-5 fw-semibold">{{ $percent }}%</div></div></div>
                        <div class="col-4"><div class="border rounded-3 p-2 h-100"><div class="small text-muted">Ditahan</div><div class="fs-5 fw-semibold text-warning">{{ $rupiah($totals[$held] ?? 0) }}</div></div></div>
                        <div class="col-4"><div class="border rounded-3 p-2 h-100"><div class="small text-muted">Sudah cair</div><div class="fs-5 fw-semibold text-success">{{ $rupiah($totals[$released] ?? 0) }}</div></div></div>
                    </div>

                    <form method="POST" action="{{ route('profile.referral.share') }}" class="border rounded-3 p-3 bg-light">
                        @csrf
                        @method('PUT')
                        <label for="share_percent" class="form-label fw-semibold mb-1">Bagikan ke teman yang Anda ajak</label>
                        <div class="small text-muted mb-2">Sebagian komisi Anda bisa diberikan ke teman sebagai potongan harga paket. Isi 0 kalau seluruh komisi untuk Anda.</div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="input-group" style="width: 140px">
                                <input type="number" id="share_percent" name="share_percent" min="0" max="{{ $percent }}" step="1" required
                                    class="form-control @error('share_percent') is-invalid @enderror" value="{{ old('share_percent', $share) }}">
                                <span class="input-group-text">%</span>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <span class="small" id="share-preview" data-percent="{{ $percent }}"></span>
                        </div>
                        @error('share_percent') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </form>

                    <div class="small text-muted mt-3">
                        <i class="ri-information-line"></i> Komisi ditahan {{ $setting->hold_days }} hari setelah pembelian (untuk berjaga kalau ada komplain/refund), lalu otomatis masuk ke saldo Anda.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-0">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Komisi</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 text-nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Teman</th>
                                    <th>Paket</th>
                                    <th class="text-end">Dibayar Teman</th>
                                    <th class="text-end">Komisi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($commissions as $commission)
                                    <tr>
                                        <td>{{ $commission->created_at->translatedFormat('d M Y') }}</td>
                                        <td>{{ $commission->buyer?->name ?? '-' }}</td>
                                        <td>{{ $commission->package_name }}</td>
                                        <td class="text-end">
                                            {{ $rupiah($commission->paid_amount) }}
                                            @if ($commission->discount_amount)
                                                <div class="small text-muted">potongan {{ $commission->share_percent }}%</div>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ $rupiah($commission->amount) }} <span class="small text-muted fw-normal">({{ $commission->commission_percent - $commission->share_percent }}%)</span></td>
                                        <td>
                                            <span class="badge {{ $commission->statusBadge() }}">{{ $commission->statusLabel() }}</span>
                                            <div class="small text-muted">
                                                @if ($commission->status === $held)
                                                    cair {{ $commission->release_at->translatedFormat('d M Y') }}
                                                @elseif ($commission->cancel_reason)
                                                    {{ $commission->cancel_reason }}
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada komisi. Komisi muncul saat teman yang Anda ajak membeli paket.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $commissions->links() }}</div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Orang yang Anda Ajak</h5>
                        <span class="badge bg-primary-subtle text-primary">{{ $referrals->total() }} orang</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Nama</th><th>Tanggal Daftar</th><th>Komisi Berlaku Sampai</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($referrals as $referral)
                                    <tr>
                                        <td>{{ $referral->referredUser?->name ?? '-' }}</td>
                                        <td>{{ $referral->created_at->translatedFormat('d F Y') }}</td>
                                        <td class="{{ $until($referral)->isPast() ? 'text-muted' : '' }}">{{ $until($referral)->translatedFormat('d F Y') }}{{ $until($referral)->isPast() ? ' (berakhir)' : '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada yang daftar lewat link Anda. Yuk, mulai bagikan!</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $referrals->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('share_percent');
            var preview = document.getElementById('share-preview');
            var total = parseInt(preview.dataset.percent, 10);
            function refresh() {
                var share = Math.min(Math.max(parseInt(input.value, 10) || 0, 0), total);
                preview.textContent = 'Anda ' + (total - share) + '% · teman potongan ' + share + '%';
            }
            input.addEventListener('input', refresh);
            refresh();
        })();
    </script>
@endsection
