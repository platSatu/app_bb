@extends('page.public.layout', ['open' => false])

@section('title', 'Pesanan '.$order->reference)

@push('head')
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="card shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="text-muted small mb-1">{{ $page->name }} · {{ $order->reference }}</div>

            @switch($order->status)
                @case(\App\Models\TicketOrder::STATUS_PAID)
                    <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                    <h1 class="h4 mt-2">{{ $order->total > 0 ? 'Pembayaran berhasil' : 'Pendaftaran berhasil' }} 🎉</h1>
                    <p class="text-muted">E-ticket sudah dikirim ke <strong>{{ $order->email }}</strong>. Cek folder spam kalau belum masuk. Semua tiket juga bisa dibuka di bawah ini.</p>
                    @break
                @case(\App\Models\TicketOrder::STATUS_PENDING)
                    <i class="ri-time-line fs-1 text-warning"></i>
                    <h1 class="h4 mt-2">Menunggu pembayaran</h1>
                    <p class="text-muted mb-2">Selesaikan pembayaran sebelum waktu habis. Tiket Anda sedang kami tahan.</p>
                    <div class="fs-3 fw-semibold mb-3" id="countdown" data-deadline="{{ $order->expires_at->getTimestamp() }}" data-now="{{ now()->getTimestamp() }}">--:--</div>
                    @if ($order->payment_url)
                        <a href="{{ $order->payment_url }}" class="btn btn-primary mb-2">Bayar Sekarang · Rp {{ number_format($order->total, 0, ',', '.') }}</a>
                    @endif
                    <div class="small text-muted">Sudah bayar? Halaman ini akan menampilkan e-ticket setelah pembayaran terkonfirmasi. <a href="{{ request()->url() }}">Muat ulang</a>.</div>
                    @break
                @case(\App\Models\TicketOrder::STATUS_EXPIRED)
                    <i class="ri-timer-flash-line fs-1 text-muted"></i>
                    <h1 class="h4 mt-2">Waktu pembayaran habis</h1>
                    <p class="text-muted">Tiket sudah dilepas. Silakan pesan ulang, ya.</p>
                    <a href="{{ $page->url() }}" class="btn btn-primary">Pesan Ulang</a>
                    @break
                @case(\App\Models\TicketOrder::STATUS_REFUND_NEEDED)
                    <i class="ri-error-warning-line fs-1 text-danger"></i>
                    <h1 class="h4 mt-2">Pembayaran diterima, tetapi tiket sudah habis</h1>
                    <p class="text-muted">Mohon maaf, pembayaran Anda masuk setelah batas waktu dan kuotanya sudah terjual. Penyelenggara akan menghubungi Anda untuk pengembalian dana.</p>
                    @break
                @default
                    <i class="ri-close-circle-line fs-1 text-danger"></i>
                    <h1 class="h4 mt-2">Pembayaran tidak berhasil</h1>
                    <p class="text-muted">Tidak ada dana yang terpotong. Silakan coba pesan lagi.</p>
                    <a href="{{ $page->url() }}" class="btn btn-primary">Pesan Lagi</a>
            @endswitch
        </div>
    </div>

    @if ($order->isPaid())
        <div class="mt-3">
            @foreach ($order->tickets as $ticket)
                <a href="{{ $ticket->url($page->slug) }}" class="card shadow-sm mb-2 text-body text-decoration-none">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold">{{ $ticket->category_name }} · Tiket {{ $ticket->sequence }} dari {{ $order->tickets->count() }}</div>
                            <div class="small text-muted">Kode booking {{ $ticket->booking_code }} · {{ $ticket->number }}</div>
                        </div>
                        <i class="ri-arrow-right-s-line fs-4"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                var el = document.getElementById('countdown');
                if (!el) return;
                var offset = Number(el.dataset.now) * 1000 - Date.now(), deadline = Number(el.dataset.deadline) * 1000;
                function tick() {
                    var left = Math.max(0, Math.round((deadline - (Date.now() + offset)) / 1000));
                    el.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
                    if (left === 0) { clearInterval(timer); el.textContent = 'Waktu habis'; }
                }
                var timer = setInterval(tick, 1000);
                tick();
                // Cek otomatis tiap 15 detik, siapa tahu pembayaran sudah terkonfirmasi.
                setTimeout(function () { location.reload(); }, 15000);
            })();
        </script>
    @endpush
@endsection
