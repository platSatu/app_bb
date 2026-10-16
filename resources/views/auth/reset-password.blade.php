@extends('layouts.auth')

@section('title', 'Buat Password Baru')
@section('image', 'login.jpg')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Buat password baru</h5>
        <p class="text-muted mb-0">Gunakan password yang kuat dan belum pernah Anda pakai sebelumnya.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-4">
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                value="{{ old('email', $request->email) }}" placeholder="Email" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Password baru', 'autocomplete' => 'new-password'])
        @include('auth.partials.password-input', ['name' => 'password_confirmation', 'placeholder' => 'Ulangi password baru', 'autocomplete' => 'new-password'])

        <button type="submit" class="btn btn-primary w-100">Simpan password</button>
    </form>
@endsection
