@extends('layouts.auth')

@php
    $branchName = $link->branch->name;
@endphp

@section('title', 'Daftar Customer · '.$branchName)

@section('content')
    <div class="mb-5">
        <span class="badge bg-primary-subtle text-primary mb-2"><i class="ri-store-2-line"></i> {{ $branchName }}</span>
        <h5 class="mb-2">Daftar sebagai customer</h5>
        <p class="text-muted mb-0">Semua tagihan dari {{ $branchName }} untuk Anda tampil di menu <strong>Tagihan Saya</strong>, dan bisa dibayar sekaligus secara online.</p>
    </div>

    @foreach (['error' => 'danger', 'info' => 'info'] as $key => $color)
        @if (session($key))
            <div class="alert alert-{{ $color }}">{{ session($key) }}</div>
        @endif
    @endforeach

    @if (! $link->is_active)
        <div class="alert alert-warning d-flex gap-2">
            <i class="ri-lock-line fs-5"></i>
            <div>Pendaftaran di {{ $branchName }} sedang ditutup. Silakan hubungi penagih untuk link yang baru.</div>
        </div>
    @elseif ($user?->isPlatformTeam())
        <div class="alert alert-info mb-0">Akun tim {{ config('app.name') }} tidak bisa mendaftar lewat link ini.</div>
    @else
        <form method="POST" action="{{ route('bills.register.store', $link->token) }}" onsubmit="this.querySelector('[type=submit]').disabled = true;">
            @csrf

            @if ($user)
                <div class="d-flex align-items-center gap-3 p-3 border rounded-3 mb-4 bg-light">
                    <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold" style="width:40px;height:40px">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}
                    </span>
                    <div class="small">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="text-muted">{{ $user->email }}</div>
                    </div>
                </div>
            @else
                <div class="mb-3">
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Nama lengkap" required maxlength="150" autocomplete="name">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Email" required autocomplete="username">
                    <div class="form-text">Pakai email yang Anda berikan ke penagih, supaya tagihannya langsung terhubung.</div>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">+62</span>
                        <input type="text" inputmode="numeric" class="form-control @error('handphone') is-invalid @enderror" name="handphone"
                            value="{{ preg_replace('/^62/', '', (string) old('handphone')) }}" placeholder="81234567890" required maxlength="16" autocomplete="tel-national">
                    </div>
                    @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                </div>
                @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Buat password', 'autocomplete' => 'new-password'])
                @include('auth.partials.password-input', ['name' => 'password_confirmation', 'placeholder' => 'Ulangi password', 'autocomplete' => 'new-password'])
                <div class="form-check mb-3">
                    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms" value="1" required @checked(old('terms'))>
                    <label class="form-check-label fs-14" for="terms">
                        Saya menyetujui Syarat dan Ketentuan serta
                        <a href="{{ config('services.frontend.url') }}/page/kebijakan-privasi" target="_blank" rel="noopener">Kebijakan Privasi</a>
                    </label>
                    @error('terms') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                </div>
                @include('auth.partials.turnstile')
            @endif

            <button type="submit" class="btn btn-primary w-100">{{ $user ? 'Gabung sebagai Customer' : 'Daftar Sekarang' }}</button>
        </form>

        @unless ($user)
            <p class="text-center text-muted fs-14 mt-5 mb-0">
                Sudah punya akun? <a href="{{ route('login') }}" class="link link-primary">Masuk dulu</a>, nanti kembali ke halaman ini.
            </p>
        @endunless
    @endif
@endsection
