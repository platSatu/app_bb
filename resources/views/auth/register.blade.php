@extends('layouts.auth')

@section('title', 'Daftar')
@section('image', 'login_new.jpg')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Buat akun baru</h5>
        <p class="text-muted mb-0">Cukup beberapa langkah untuk mulai memakai {{ config('app.name') }}.</p>
    </div>

    @if ($referralCode)
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="ri-gift-line fs-5"></i>
            <div>
                Anda diundang oleh <strong>{{ $referralCode->user->name }}</strong>.
                @if (($referralSetting = \App\Models\ReferralSetting::current())->is_active && ($share = $referralCode->sharePercent($referralSetting)))
                    Dapat potongan <strong>{{ $share }}%</strong> setiap beli paket selama {{ $referralSetting->active_months }} bulan.
                @endif
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-4">
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                value="{{ old('name') }}" placeholder="Nama lengkap" required autofocus autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                value="{{ old('email') }}" placeholder="Email" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <div class="input-group">
                <span class="input-group-text">+62</span>
                <input type="text" inputmode="numeric" class="form-control @error('handphone') is-invalid @enderror"
                    id="handphone" name="handphone" value="{{ preg_replace('/^62/', '', (string) old('handphone')) }}"
                    placeholder="81234567890" required maxlength="16" autocomplete="tel-national">
            </div>
            <div class="form-text">Nomor WhatsApp aktif, tanpa angka 0 di depan.</div>
            @error('handphone')
                <div class="text-danger fs-13 mt-1">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Password', 'autocomplete' => 'new-password'])
        @include('auth.partials.password-input', ['name' => 'password_confirmation', 'placeholder' => 'Ulangi password', 'autocomplete' => 'new-password'])

        {{-- Checkbox terkunci sampai Syarat & Ketentuan dibuka (popup); menutup popup = sudah dibaca, otomatis tercentang. --}}
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" required
                @checked(old('terms')) @disabled(! old('terms'))>
            <label class="form-check-label fs-14" for="terms">
                Saya menyetujui
                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat dan Ketentuan</a> serta
                <a href="{{ config('services.frontend.url') }}/page/kebijakan-privasi" target="_blank" rel="noopener">Kebijakan Privasi</a>
            </label>
            <div class="form-text" id="termsHint" @if (old('terms')) style="display:none" @endif>Klik <strong>Syarat dan Ketentuan</strong> untuk membacanya terlebih dahulu.</div>
            @error('terms')
                <div class="text-danger fs-13 mt-1">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.turnstile')

        <button type="submit" class="btn btn-primary w-100">Daftar</button>
    </form>

    @include('auth.partials.google-button', ['label' => 'Daftar dengan Google'])

    @push('modals')
        <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="termsModalTitle">{{ $terms->name ?? 'Syarat dan Ketentuan' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body terms-body">
                        @if ($terms)
                            {!! $terms->descriptionsHtml() !!}
                        @else
                            <p class="text-muted mb-0">Syarat dan Ketentuan belum tersedia.</p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .terms-body h2 { font-size: 1rem; font-weight: 600; margin: 1.25rem 0 .5rem; }
            .terms-body h3 { font-size: .9375rem; font-weight: 600; margin: 1rem 0 .5rem; }
            .terms-body ol, .terms-body ul { padding-left: 1.25rem; }
        </style>
    @endpush

    @push('scripts')
        <script>
            (function () {
                var modal = document.getElementById('termsModal');
                var checkbox = document.getElementById('terms');
                var hint = document.getElementById('termsHint');
                if (!modal || !checkbox) return;
                // Semua cara menutup popup (tombol, X, klik luar, Esc) memicu event ini.
                modal.addEventListener('hidden.bs.modal', function () {
                    checkbox.disabled = false;
                    checkbox.checked = true;
                    if (hint) hint.style.display = 'none';
                });
            })();
        </script>
    @endpush

    <p class="text-center text-muted fs-14 my-6">
        Sudah punya akun? <a href="{{ route('login') }}" class="link link-primary">Masuk di sini</a>
    </p>
@endsection
