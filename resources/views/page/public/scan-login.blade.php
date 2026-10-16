@extends('page.public.layout', ['page' => $scanner->page, 'open' => false])

@section('title', 'Registrasi Ulang · '.$scanner->page->name)
@section('width', '420px')

@push('head')
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="ri-qr-scan-2-line fs-1 text-primary"></i>
            <h1 class="h5 mt-2 mb-1">Registrasi Ulang</h1>
            <p class="text-muted small mb-4">{{ $scanner->page->name }} · Petugas {{ $scanner->name }}</p>
            <form method="POST" action="{{ route('scan.login', request()->route('token')) }}">
                @csrf
                <input type="password" name="pin" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus
                    class="form-control form-control-lg text-center fs-3 mb-2 @error('pin') is-invalid @enderror" placeholder="••••••" style="letter-spacing: .4em;">
                @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-primary w-100 mt-2">Masuk</button>
            </form>
            <p class="small text-muted mt-3 mb-0">PIN 6 angka diberikan oleh penyelenggara event.</p>
        </div>
    </div>
@endsection
