@extends('whatsapp.google-form.public.layout')

@section('title', 'QR Registrasi · '.$integration->name)

@section('content')
    <div class="card border-0 shadow-sm rounded-4 text-center">
        <div class="card-body p-4 p-md-5">
            <div class="small text-muted">{{ $integration->branch?->company?->name }}</div>
            <h1 class="h4 fw-bold mb-1">{{ $integration->name }}</h1>
            <div class="mb-4">{{ $submission->displayName() }}</div>

            @if ($qr)
                <img src="{{ $qr }}" alt="QR registrasi ulang" class="img-fluid mx-auto d-block" style="max-width: 260px;">
                <div class="font-monospace fs-4 fw-semibold mt-2" style="letter-spacing: .15em;">{{ $submission->code }}</div>
                @if ($submission->attended_at)
                    <div class="badge bg-success-subtle text-success fs-6 mt-3"><i class="ri-checkbox-circle-line"></i> Sudah registrasi ulang {{ $submission->attended_at->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}</div>
                @else
                    <p class="small text-muted mt-3 mb-0">Tunjukkan QR ini ke petugas saat registrasi ulang. Simpan halaman ini atau screenshot QR-nya.</p>
                @endif
            @else
                <span class="icon-circle mb-3"><i class="ri-time-line"></i></span>
                <p class="mb-3">QR registrasi ulang muncul setelah pembayaran diterima.</p>
                @if ($submission->invoice?->token)
                    <a href="{{ $submission->invoice->payUrl() }}" class="btn btn-brand px-4">Bayar sekarang</a>
                @endif
            @endif
        </div>
    </div>
@endsection
