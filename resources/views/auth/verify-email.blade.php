@extends('layouts.auth')

@section('title', 'Verifikasi Email')
@section('image', 'login.jpg')

@section('content')
    <div class="mb-6">
        <h5 class="mb-2">Cek email Anda</h5>
        <p class="text-muted mb-0">
            Kami sudah mengirim link verifikasi ke email Anda. Klik link tersebut untuk mengaktifkan akun.
            Belum masuk? Cek juga folder spam, atau kirim ulang di bawah ini.
        </p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success">Link verifikasi baru sudah kami kirim. Silakan cek email Anda.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="mb-3">
        @csrf
        <button type="submit" class="btn btn-primary w-100">Kirim ulang email verifikasi</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-light w-100">Keluar</button>
    </form>
@endsection
