@extends('layouts.auth')

@section('title', 'Konfirmasi Password')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Konfirmasi password</h5>
        <p class="text-muted mb-0">Demi keamanan, masukkan lagi password Anda sebelum melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Password', 'autocomplete' => 'current-password'])
        <button type="submit" class="btn btn-primary w-100">Lanjutkan</button>
    </form>
@endsection
