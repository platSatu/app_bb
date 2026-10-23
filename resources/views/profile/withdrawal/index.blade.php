@extends('layouts.dashboard')

@section('title', 'My Account')

@php($rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    @include('profile._tabs', ['active' => 'withdrawal'])

    @unless ($available)
        <div class="alert alert-info">
            <i class="ri-information-line me-1"></i>Fitur tarik saldo sedang disiapkan. Saldo Anda tetap aman dan bisa ditarik begitu fitur ini aktif.
        </div>
    @endunless

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Rekening Tujuan</h6>

                    @if ($approved)
                        <div class="border rounded p-3 mb-3">
                            <div class="fw-semibold">{{ $approved->masked() }}</div>
                            <div class="text-muted">a.n. {{ $approved->account_name }}</div>
                            <span class="badge bg-success-subtle text-success mt-1">Terverifikasi</span>
                        </div>
                    @endif

                    @if ($pending)
                        <div class="alert alert-warning py-2">
                            <i class="ri-time-line me-1"></i>Rekening <strong>{{ $pending->masked() }}</strong> a.n. {{ $pending->account_name }} sedang diverifikasi admin.
                            @if ($approved) Setelah disetujui, rekening ini menggantikan rekening di atas. @endif
                        </div>
                    @elseif ($rejected && ! $approved)
                        <div class="alert alert-danger py-2">
                            Rekening {{ $rejected->masked() }} belum bisa kami setujui{{ $rejected->review_note ? ': '.$rejected->review_note : '.' }}
                            Silakan ajukan rekening lain.
                        </div>
                    @endif

                    @if ($available)
                        @if ($banksError)
                            <div class="alert alert-warning py-2">{{ $banksError }}</div>
                        @else
                            <button class="btn btn-sm btn-outline-primary {{ $approved || $pending ? '' : 'd-none' }}" type="button" data-bs-toggle="collapse" data-bs-target="#account-form">
                                <i class="ri-edit-line me-1"></i>{{ $approved || $pending ? 'Ganti rekening' : 'Tambah rekening' }}
                            </button>
                            <form method="POST" action="{{ route('account.withdrawals.account') }}" id="account-form" class="collapse {{ $approved || $pending ? '' : 'show' }} mt-3">
                                @csrf
                                <div class="mb-2">
                                    <label class="form-label">Bank</label>
                                    <select name="bank_code" class="form-select @error('bank_code') is-invalid @enderror" required>
                                        <option value="">Pilih bank</option>
                                        @foreach ($banks as $bank)
                                            <option value="{{ $bank['bankCode'] }}" @selected(old('bank_code') === $bank['bankCode'])>{{ $bank['bankName'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('bank_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Nomor rekening</label>
                                    <input type="text" name="account_number" inputmode="numeric" maxlength="30" value="{{ old('account_number') }}" class="form-control @error('account_number') is-invalid @enderror" required>
                                    @error('account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text mb-2">Nama pemilik rekening diambil langsung dari bank, lalu dicek admin. Pastikan rekening atas nama Anda sendiri.</div>
                                <button type="submit" class="btn btn-primary">Cek & Ajukan Rekening</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Tarik Saldo</h6>
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="d-flex justify-content-between"><span class="text-muted">Saldo Anda</span><span class="fw-semibold">{{ $rupiah($balance) }}</span></div>
                        <div class="d-flex justify-content-between mt-1"><span class="text-muted">Yang bisa ditarik saat ini</span><span class="fw-bold text-primary">{{ $rupiah($withdrawable) }}</span></div>
                        @if ($pendingItems->isNotEmpty())
                            <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#pending-settlement" role="button" aria-expanded="false">Kenapa belum bisa ditarik semua?</a>
                            <div class="collapse" id="pending-settlement">
                                <div class="small text-muted mt-2 mb-1">Sebagian pembayaran masih dalam proses pencairan dari bank / e-wallet:</div>
                                @foreach ($pendingItems as $item)
                                    <div class="d-flex justify-content-between small">
                                        <span>{{ $gateway->methodName($item->payment_method) ?? 'Pembayaran' }} · {{ $rupiah($item->amount) }}</span>
                                        <span class="text-muted">bisa ditarik {{ $item->available_at->translatedFormat('d M, H:i') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (! $available)
                        <p class="text-muted mb-0">Belum tersedia.</p>
                    @elseif (! $approved)
                        <p class="text-muted mb-0">Tambahkan rekening dan tunggu verifikasi admin dulu, setelah itu Anda bisa tarik saldo di sini.</p>
                    @else
                        <form method="POST" action="{{ route('account.withdrawals.store') }}" id="withdraw-form"
                            data-fee="{{ $setting->withdrawal_fee }}" data-min="{{ $setting->min_amount }}"
                            onsubmit="return confirm({{ Js::from('Ajukan tarik saldo ke '.$approved->masked().'?') }})">
                            @csrf
                            <input type="hidden" name="fee" value="{{ $setting->withdrawal_fee }}">
                            <label class="form-label">Jumlah yang ditarik</label>
                            <div class="input-group mb-1">
                                <span class="input-group-text">Rp</span>
                                <input type="text" name="amount" data-rupiah value="{{ old('amount') }}"
                                    class="form-control @error('amount') is-invalid @enderror" required>
                                @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text mb-3">Minimal {{ $rupiah($setting->min_amount) }}. Biaya penarikan {{ $rupiah($setting->withdrawal_fee) }}.</div>

                            <div class="bg-light rounded p-3 mb-3 small">
                                <div class="d-flex justify-content-between"><span>Saldo dipotong</span><span data-out="amount">Rp 0</span></div>
                                <div class="d-flex justify-content-between"><span>Biaya</span><span>-{{ $rupiah($setting->withdrawal_fee) }}</span></div>
                                <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1"><span>Diterima di rekening</span><span data-out="net">Rp 0</span></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" @disabled($withdrawable < $setting->min_amount)>Ajukan Tarik Saldo</button>
                            @if ($withdrawable < $setting->min_amount)
                                <div class="form-text text-center">{{ $balance >= $setting->min_amount ? 'Saldo yang sudah cair belum mencapai minimal penarikan. Tunggu sebentar, ya.' : 'Saldo Anda belum mencapai minimal penarikan.' }}</div>
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Pengajuan Tarik Saldo</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>Rekening</th>
                            <th class="text-end">Ditarik</th>
                            <th class="text-end">Diterima</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($withdrawals as $withdrawal)
                            <tr>
                                <td class="fw-semibold">{{ $withdrawal->reference }}</td>
                                <td>{{ $withdrawal->bank_name }} ••••{{ $withdrawal->account_last4 }}</td>
                                <td class="text-end">{{ $rupiah($withdrawal->amount) }}</td>
                                <td class="text-end">{{ $rupiah($withdrawal->net_amount) }}</td>
                                <td>
                                    <span class="badge {{ $withdrawal->statusBadge() }}">{{ $withdrawal->statusLabel() }}</span>
                                    @if ($withdrawal->rejection_reason)
                                        <div class="text-muted small text-wrap">{{ $withdrawal->rejection_reason }}</div>
                                    @elseif ($withdrawal->status === \App\Models\WalletWithdrawal::STATUS_NEEDS_REVIEW)
                                        <div class="text-muted small text-wrap">Admin sedang memastikan dana sampai. Saldo tetap aman.</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $withdrawal->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($withdrawal->status === \App\Models\WalletWithdrawal::STATUS_PENDING)
                                        <form method="POST" action="{{ route('account.withdrawals.cancel', $withdrawal) }}" onsubmit="return confirm('Batalkan pengajuan ini? Saldo akan dikembalikan.')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger">Batalkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan tarik saldo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $withdrawals->links() }}</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('withdraw-form');
            if (!form) return;
            const fee = Number(form.dataset.fee), input = form.querySelector('[name=amount]');
            const fmt = v => 'Rp ' + Math.max(0, v).toLocaleString('id-ID');
            const update = () => {
                const amount = window.rupiahValue(input);
                form.querySelector('[data-out=amount]').textContent = fmt(amount);
                form.querySelector('[data-out=net]').textContent = fmt(amount - fee);
            };
            input.addEventListener('input', update);
            update();
        })();
    </script>
@endpush
