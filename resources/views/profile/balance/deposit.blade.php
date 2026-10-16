@extends('layouts.dashboard')

@section('title', 'Status Top Up')
@section('page-section', 'My Account')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body text-center py-5">
                    @if ($deposit->status === \App\Models\Deposit::STATUS_SUCCESS)
                        <i class="ri-checkbox-circle-line text-success display-4"></i>
                        <h5 class="mt-3">Top up berhasil</h5>
                        <p class="text-muted">Saldo Rp {{ number_format($deposit->amount, 0, ',', '.') }} sudah masuk ke akun Anda.</p>
                    @elseif ($deposit->isPending())
                        <i class="ri-time-line text-warning display-4"></i>
                        <h5 class="mt-3">Menunggu pembayaran</h5>
                        <p class="text-muted">Saldo akan masuk otomatis begitu pembayaran dikonfirmasi. Halaman ini bisa dimuat ulang untuk cek status.</p>
                        @if ($deposit->payment_url && $deposit->expires_at?->isFuture())
                            <a href="{{ $deposit->payment_url }}" class="btn btn-primary mb-3">Lanjutkan Pembayaran</a>
                        @endif
                    @else
                        <i class="ri-close-circle-line text-danger display-4"></i>
                        <h5 class="mt-3">Top up {{ strtolower($deposit->statusLabel()) }}</h5>
                        <p class="text-muted">Tidak ada saldo yang terpotong. Silakan coba top up lagi.</p>
                    @endif

                    <div class="border rounded-3 p-3 text-start my-3">
                        <div class="d-flex justify-content-between"><span class="text-muted">No. Referensi</span><strong>{{ $deposit->reference_number }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Nominal</span><strong>Rp {{ number_format($deposit->amount, 0, ',', '.') }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Status</span><span class="badge {{ $deposit->statusBadge() }}">{{ $deposit->statusLabel() }}</span></div>
                    </div>

                    <a href="{{ route('profile.balance') }}" class="btn btn-light">Kembali ke Saldo</a>
                </div>
            </div>
        </div>
    </div>
@endsection
