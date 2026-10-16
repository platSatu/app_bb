@extends('layouts.dashboard')

@section('title', 'Tambah Saldo')
@section('page-section', 'My Account')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    @if (! $setting->isReady())
                        <div class="alert alert-warning mb-0">Pembayaran online belum aktif. Silakan hubungi admin kami.</div>
                    @else
                        <h5 class="mb-1">Tambah Saldo</h5>
                        <p class="text-muted mb-4">Pilih atau ketik nominal, lalu lanjutkan ke halaman pembayaran.</p>

                        <form method="POST" action="{{ route('account.deposits.store') }}">
                            @csrf
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach ([50000, 100000, 250000, 500000, 1000000] as $preset)
                                    @if ($preset >= $setting->min_deposit && $preset <= $setting->max_deposit)
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-amount="{{ $preset }}">
                                            Rp {{ number_format($preset, 0, ',', '.') }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>

                            <label for="amount" class="form-label">Nominal</label>
                            <div class="input-group mb-1">
                                <span class="input-group-text">Rp</span>
                                <input type="text" inputmode="numeric" id="amount" name="amount" required
                                    class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" placeholder="50000">
                            </div>
                            @error('amount') <div class="text-danger fs-13 mb-2">{{ $message }}</div> @enderror
                            <div class="form-text mb-4">
                                Minimal Rp {{ number_format($setting->min_deposit, 0, ',', '.') }},
                                maksimal Rp {{ number_format($setting->max_deposit, 0, ',', '.') }}.
                                Saldo masuk otomatis setelah pembayaran berhasil.
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('profile.balance') }}" class="btn btn-light">Batal</a>
                                <button type="submit" class="btn btn-primary">Lanjut Bayar</button>
                            </div>
                        </form>

                        <script>
                            document.querySelectorAll('[data-amount]').forEach(function (button) {
                                button.addEventListener('click', function () {
                                    document.getElementById('amount').value = button.dataset.amount;
                                });
                            });
                        </script>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
