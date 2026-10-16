@extends('layouts.auth')

@section('title', 'Lupa Password')
@section('image', 'login_new.jpg')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Lupa password?</h5>
        <p class="text-muted mb-0">Tidak apa-apa. Masukkan email akun Anda, nanti kami kirimkan link untuk membuat password baru.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                value="{{ old('email') }}" placeholder="Email" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.turnstile')

        <button type="submit" class="btn btn-primary w-100">Kirim link reset</button>
    </form>

    <p class="text-center text-muted fs-14 my-6">
        Sudah ingat? <a href="{{ route('login') }}" class="link link-primary">Kembali ke halaman masuk</a>
    </p>
@endsection
