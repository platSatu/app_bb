@extends('whatsapp.google-form.public.layout')

@section('title', 'Registrasi Ulang · '.$integration->name)

@section('content')
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="icon-circle mb-3"><i class="ri-qr-scan-2-line"></i></span>
            <h1 class="h4 fw-bold mb-1">Registrasi Ulang</h1>
            <p class="text-muted mb-4">{{ $integration->name }}</p>
            <form method="POST" action="{{ route('form-checkin.login', request()->route('token')) }}" onsubmit="this.querySelector('[type=submit]').disabled = true">
                @csrf
                <label for="pin" class="form-label small text-muted">Masukkan PIN 6 angka</label>
                <input type="password" id="pin" name="pin" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus
                    class="form-control form-control-lg text-center fs-3 @error('pin') is-invalid @enderror" style="letter-spacing: .5em;" placeholder="••••••">
                @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-brand w-100 py-2 mt-3"><i class="ri-login-box-line me-1"></i>Masuk</button>
            </form>
            <p class="small text-muted mt-4 mb-0">PIN diberikan oleh penyelenggara acara.</p>
        </div>
    </div>
@endsection
