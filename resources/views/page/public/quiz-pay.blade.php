{{--
    Layar tunggu pembayaran quiz. Lunas atau tidaknya HANYA ditentukan callback Duitku, bukan
    halaman ini -- peserta yang menyegarkan layar atau menekan kembali tidak bisa melewatinya.
--}}
@extends('page.public.layout')

@section('title', 'Menunggu pembayaran')
@section('width', '720px')

@section('content')
    <div class="card shadow-sm" style="border: 0; border-radius: 1rem;">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="d-inline-flex align-items-center justify-content-center mb-3"
                style="width: 64px; height: 64px; border-radius: 50%; font-size: 1.9rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand);">
                <i class="ri-bank-card-line"></i>
            </span>

            <h1 class="h4 fw-bold mb-1">Selesaikan pembayaran dulu ya</h1>
            <p class="text-muted mb-4">{{ $page->name }}</p>

            <div class="mx-auto mb-4" style="max-width: 360px;">
                <div class="text-muted small mb-1">Biaya mengikuti quiz</div>
                <div class="display-6 fw-bold">Rp {{ number_format((int) $attempt->amount, 0, ',', '.') }}</div>
            </div>

            @if ($attempt->payment_url)
                <a href="{{ $attempt->payment_url }}" class="btn btn-brand px-4 py-2">
                    <i class="ri-external-link-line me-1"></i>Bayar sekarang
                </a>
            @endif

            <div class="mt-4">
                <a href="{{ route('page.quiz.play', $page->slug) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-refresh-line me-1"></i>Sudah bayar, muat ulang
                </a>
            </div>

            <p class="text-muted small mt-4 mb-0">
                Soal terbuka otomatis begitu pembayaran Anda dikonfirmasi. Kalau sudah membayar tapi
                halaman ini belum berubah, tunggu sebentar lalu muat ulang.
            </p>

            <span class="d-inline-block mt-3" style="background: #f3f4f6; border-radius: 999px; padding: .25rem .75rem; font-size: .8125rem;">
                <i class="ri-hashtag me-1"></i>{{ $attempt->reference }}
            </span>
        </div>
    </div>
@endsection
