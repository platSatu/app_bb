@extends('page.public.layout', ['open' => false])

@section('title', 'Pesanan '.$order->reference)
@section('width', '1000px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <style>
        .order-card { border: 0; border-radius: 1rem; }
        .status-icon { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.25rem; }
        .ticket-link { border: 1px solid #eef0f4; border-radius: .875rem; transition: border-color .15s, transform .15s, box-shadow .15s; }
        .ticket-link:hover { border-color: var(--brand); transform: translateY(-1px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.06); }
        .ticket-stub { width: 46px; height: 46px; border-radius: .75rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .chip { background: #f3f4f6; border-radius: 999px; padding: .25rem .75rem; font-size: .8125rem; }
    </style>
@endpush

@section('content')
    @php
        $rupiah = fn ($n) => $n > 0 ? 'Rp '.number_format($n, 0, ',', '.') : 'Gratis';
        [$icon, $tone] = match ($order->status) {
            \App\Models\TicketOrder::STATUS_PAID => ['ri-checkbox-circle-line', 'bg-success-subtle text-success'],
            \App\Models\TicketOrder::STATUS_PENDING => ['ri-time-line', 'bg-warning-subtle text-warning'],
            \App\Models\TicketOrder::STATUS_EXPIRED => ['ri-timer-flash-line', 'bg-secondary-subtle text-secondary'],
            default => ['ri-error-warning-line', 'bg-danger-subtle text-danger'],
        };
    @endphp

    <div class="card order-card shadow-sm mb-4">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="status-icon {{ $tone }} mb-3"><i class="{{ $icon }}"></i></span>

            @switch($order->status)
                @case(\App\Models\TicketOrder::STATUS_PAID)
                    <h1 class="h4 fw-bold mb-2">{{ $order->total > 0 ? 'Pembayaran berhasil' : 'Pendaftaran berhasil' }} 🎉</h1>
                    <p class="text-muted mb-0">E-ticket sudah dikirim ke <strong class="text-body">{{ $order->email }}</strong>.<br class="d-none d-md-inline"> Cek folder spam kalau belum masuk, atau buka langsung di bawah ini.</p>
                    @break
                @case(\App\Models\TicketOrder::STATUS_PENDING)
                    <h1 class="h4 fw-bold mb-2">Menunggu pembayaran</h1>
                    <p class="text-muted mb-3">Selesaikan pembayaran sebelum waktu habis. Tiket Anda sedang kami tahan.</p>
                    <div class="display-6 fw-semibold mb-3" id="countdown" data-deadline="{{ $order->expires_at->getTimestamp() }}" data-now="{{ now()->getTimestamp() }}">--:--</div>
                    @if ($order->payment_url)
                        <a href="{{ $order->payment_url }}" class="btn btn-brand px-4 py-2 mb-2">Bayar Sekarang · {{ $rupiah($order->total) }}</a>
                    @endif
                    <div class="small text-muted">Sudah bayar? Halaman ini otomatis menampilkan e-ticket setelah pembayaran terkonfirmasi. <a href="{{ request()->url() }}">Muat ulang</a></div>
                    @break
                @case(\App\Models\TicketOrder::STATUS_EXPIRED)
                    <h1 class="h4 fw-bold mb-2">Waktu pembayaran habis</h1>
                    <p class="text-muted mb-3">Tiket sudah dilepas. Silakan pesan ulang, ya.</p>
                    <a href="{{ $page->url() }}" class="btn btn-brand px-4">Pesan Ulang</a>
                    @break
                @case(\App\Models\TicketOrder::STATUS_REFUND_NEEDED)
                    <h1 class="h4 fw-bold mb-2">Pembayaran diterima, tetapi tiket sudah habis</h1>
                    <p class="text-muted mb-0">Mohon maaf, pembayaran Anda masuk setelah batas waktu dan kuotanya sudah terjual. Penyelenggara akan menghubungi Anda untuk pengembalian dana.</p>
                    @break
                @default
                    <h1 class="h4 fw-bold mb-2">Pembayaran tidak berhasil</h1>
                    <p class="text-muted mb-3">Tidak ada dana yang terpotong. Silakan coba pesan lagi.</p>
                    <a href="{{ $page->url() }}" class="btn btn-brand px-4">Pesan Lagi</a>
            @endswitch

            <div class="d-flex justify-content-center flex-wrap gap-2 mt-4">
                <span class="chip"><i class="ri-calendar-event-line me-1"></i>{{ $page->name }}</span>
                <span class="chip"><i class="ri-hashtag me-1"></i>{{ $order->reference }}</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7 order-2 order-lg-1">
            @if ($order->isPaid())
                <div class="card order-card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-semibold mb-3">E-ticket Anda <span class="text-muted fw-normal">({{ $order->tickets->count() }})</span></h2>
                        @foreach ($order->tickets as $ticket)
                            <a href="{{ $ticket->url($page->slug) }}" class="ticket-link d-flex align-items-center gap-3 p-3 text-body text-decoration-none {{ $loop->last ? '' : 'mb-2' }}">
                                <span class="ticket-stub flex-shrink-0"><i class="ri-coupon-3-line"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold">{{ $ticket->category_name }} <span class="text-muted fw-normal small">· Tiket {{ $ticket->sequence }} dari {{ $order->tickets->count() }}</span></div>
                                    <div class="small text-muted">Kode booking <span class="fw-semibold text-body">{{ $ticket->booking_code }}</span> · {{ $ticket->number }}</div>
                                </div>
                                <span class="btn btn-sm btn-light flex-shrink-0">Buka <i class="ri-arrow-right-s-line"></i></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="card order-card shadow-sm">
                    <div class="card-body p-4 text-muted small">
                        <h2 class="h6 fw-semibold text-body mb-2">Butuh bantuan?</h2>
                        Simpan halaman ini. E-ticket akan muncul di sini dan dikirim ke <strong class="text-body">{{ $order->email }}</strong> setelah pembayaran terkonfirmasi.
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5 order-1 order-lg-2">
            <div class="card order-card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h6 fw-semibold mb-3">Ringkasan Pesanan</h2>
                    @foreach ($order->items as $item)
                        <div class="d-flex justify-content-between gap-2 small mb-2">
                            <span>{{ $item->name }} × {{ $item->quantity }}</span>
                            <span class="text-nowrap">{{ $rupiah($item->subtotal) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between align-items-center fw-semibold mb-3">
                        <span>Total</span><span class="fs-5">{{ $rupiah($order->total) }}</span>
                    </div>
                    <dl class="row small mb-0 gy-1">
                        <dt class="col-5 text-muted fw-normal">Nama</dt><dd class="col-7 text-end mb-0 text-break">{{ $order->name }}</dd>
                        <dt class="col-5 text-muted fw-normal">WhatsApp</dt><dd class="col-7 text-end mb-0">{{ $order->phone }}</dd>
                        @if ($order->paid_at)
                            <dt class="col-5 text-muted fw-normal">{{ $order->total > 0 ? 'Dibayar' : 'Terdaftar' }}</dt><dd class="col-7 text-end mb-0">{{ $order->paid_at->translatedFormat('d M Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>

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
