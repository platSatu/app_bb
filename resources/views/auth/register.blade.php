@extends('layouts.auth')

@section('title', 'Daftar')
@section('image', 'login.jpg')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Buat akun baru</h5>
        <p class="text-muted mb-0">Cukup beberapa langkah untuk mulai memakai {{ config('app.name') }}.</p>
    </div>

    @if ($referralCode)
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="ri-gift-line fs-5"></i>
            <div>Anda diundang oleh <strong>{{ $referralCode->user->name }}</strong>.</div>
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

        @include('auth.partials.turnstile')

        <button type="submit" class="btn btn-primary w-100">Daftar</button>
    </form>

    <p class="text-center text-muted fs-14 my-6">
        Sudah punya akun? <a href="{{ route('login') }}" class="link link-primary">Masuk di sini</a>
    </p>
@endsection
