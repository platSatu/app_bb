@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Selamat datang kembali</h5>
        <p class="text-muted mb-0">Masuk untuk mengelola bisnis Anda di {{ config('app.name') }}.</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-4">
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                value="{{ old('email') }}" placeholder="Email" required autofocus autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Password', 'autocomplete' => 'current-password'])

        <div class="mb-4 d-flex flex-wrap justify-content-between align-items-center">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <a href="{{ route('password.request') }}" class="link link-primary ms-auto">Lupa password?</a>
        </div>

        @include('auth.partials.turnstile')

        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>

    <p class="text-center text-muted fs-14 my-6">
        Belum punya akun? <a href="{{ route('register') }}" class="link link-primary">Daftar sekarang</a>
    </p>
@endsection
