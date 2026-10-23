@extends('page.public.layout', ['page' => $scanner->page, 'open' => false])

@php
    $heading = match ($scanner->type) {
        \App\Models\TicketScanner::TYPE_KIOSK => ['Kiosk Cetak ID Card', 'ri-printer-line', 'Kiosk'],
        \App\Models\TicketScanner::TYPE_ATTENDANCE => ['Absensi Kelas', 'ri-calendar-check-line', 'Petugas'],
        default => ['Registrasi Ulang', 'ri-qr-scan-2-line', 'Petugas'],
    };
@endphp

@section('title', $heading[0].' · '.$scanner->page->name)
@section('width', '440px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <style>
        .login-icon { width: 76px; height: 76px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.4rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
        .pin-input { height: 64px; border-radius: .875rem; border: 2px solid #e9ecef; letter-spacing: .5em; }
        .pin-input:focus { border-color: var(--brand); box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--brand) 15%, transparent); }
    </style>
@endpush

@section('content')
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="login-icon mb-3"><i class="{{ $heading[1] }}"></i></span>
            <h1 class="h4 fw-bold mb-1">{{ $heading[0] }}</h1>
            <p class="text-muted mb-1">{{ $scanner->page->name }}</p>
            <span class="badge rounded-pill bg-light text-dark mb-4"><i class="ri-user-location-line me-1"></i>{{ $heading[2] }} {{ $scanner->name }}</span>
            <form method="POST" action="{{ route('scan.login', request()->route('token')) }}" onsubmit="this.querySelector('[type=submit]').disabled = true">
                @csrf
                <label for="pin" class="form-label small text-muted">Masukkan PIN 6 angka</label>
                <input type="password" id="pin" name="pin" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus
                    class="form-control pin-input text-center fs-3 @error('pin') is-invalid @enderror" placeholder="••••••">
                @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-brand w-100 py-2 mt-3"><i class="ri-login-box-line me-1"></i>Masuk</button>
            </form>
            <p class="small text-muted mt-4 mb-0">PIN diberikan oleh penyelenggara event.</p>
        </div>
    </div>
@endsection
