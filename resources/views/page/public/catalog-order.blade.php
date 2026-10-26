@extends('page.public.layout', ['open' => false])

@section('title', 'Pesanan '.$order->reference)
@section('width', '860px')

@push('head')
    <meta name="referrer" content="no-referrer">
    <style>
        .order-card { border: 0; border-radius: 1rem; }
        .status-icon { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.25rem; }
        .bank-row { border: 1px solid #eef0f4; border-radius: .875rem; }
    </style>
@endpush

@push('scripts')
    {{-- Pesanan sudah dibuat: keranjang di browser pembeli dikosongkan. --}}
    <script>try { localStorage.removeItem(@json('cart:'.$page->slug)); } catch (e) {}</script>
@endpush

@section('content')
    @php
        $rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
        $awaiting = $order->status === \App\Models\CatalogOrder::STATUS_NEW;
        $online = $order->payment_type === \App\Models\CatalogOrder::PAYMENT_ONLINE;
        $canPayOnline = $awaiting && $online && $order->awaitsOnlinePayment() && $order->payment_url && $order->expires_at?->isFuture();
        [$icon, $tone] = match (true) {
            $order->status === \App\Models\CatalogOrder::STATUS_CANCELLED => ['ri-close-circle-line', 'bg-danger-subtle text-danger'],
            $awaiting && $order->payment_type !== null => ['ri-time-line', 'bg-warning-subtle text-warning'],
            default => ['ri-checkbox-circle-line', 'bg-success-subtle text-success'],
        };
    @endphp

    <div class="card order-card shadow-sm mb-4">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="status-icon {{ $tone }} mb-3"><i class="{{ $icon }}"></i></span>

            @if ($order->status === \App\Models\CatalogOrder::STATUS_CANCELLED)
                <h1 class="h4 fw-bold mb-2">Pesanan dibatalkan</h1>
                <p class="text-muted mb-3">{{ $online ? 'Waktu pembayaran habis atau pembayaran tidak berhasil. Tidak ada dana yang terpotong.' : 'Pesanan ini dibatalkan oleh penjual.' }}</p>
                <a href="{{ $page->url() }}" class="btn btn-brand px-4">Belanja Lagi</a>
            @elseif ($canPayOnline)
                <h1 class="h4 fw-bold mb-2">Menunggu pembayaran</h1>
                <p class="text-muted mb-3">Selesaikan pembayaran sebelum <strong class="text-body">{{ $order->expires_at->translatedFormat('d F Y, H:i') }}</strong>. Stok Anda sedang kami tahan.</p>
                <a href="{{ $order->payment_url }}" class="btn btn-brand px-4 py-2 mb-2">Bayar Sekarang · {{ $rupiah($order->total) }}</a>
                <div class="small text-muted">Sudah bayar? Status di halaman ini berubah otomatis setelah pembayaran terkonfirmasi. <a href="{{ request()->url() }}">Muat ulang</a></div>
            @elseif ($awaiting && $online)
                <h1 class="h4 fw-bold mb-2">Pembayaran sedang dikonfirmasi</h1>
                <p class="text-muted mb-0">Kalau Anda sudah membayar, status berubah otomatis dalam beberapa menit. <a href="{{ request()->url() }}">Muat ulang</a></p>
            @elseif ($awaiting && $order->payment_type === \App\Models\CatalogOrder::PAYMENT_TRANSFER)
                <h1 class="h4 fw-bold mb-2">Pesanan diterima, silakan transfer</h1>
                <p class="text-muted mb-0">Transfer tepat <strong class="text-body">{{ $rupiah($order->total) }}</strong> ke salah satu rekening di bawah, lalu kabari penjual dengan menyebutkan nomor pesanan.</p>
            @elseif ($awaiting)
                <h1 class="h4 fw-bold mb-2">Terima kasih, pesanan Anda sudah kami terima</h1>
                <p class="text-muted mb-0">Penjual akan menghubungi Anda untuk pembayaran & pengiriman.</p>
            @else
                <h1 class="h4 fw-bold mb-2">{{ $order->paid_at ? 'Pembayaran berhasil' : 'Pesanan '.strtolower($order->statusLabel()) }}</h1>
                <p class="text-muted mb-0">Status pesanan: <strong class="text-body">{{ $order->statusLabel() }}</strong>. Penjual akan memproses pesanan Anda.</p>
            @endif

            <p class="fs-5 fw-semibold mt-4 mb-0"><i class="ri-hashtag"></i>{{ $order->reference }}</p>
        </div>
    </div>

    @if ($awaiting && $order->payment_type === \App\Models\CatalogOrder::PAYMENT_TRANSFER)
        <div class="card order-card shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h6 fw-semibold mb-3">Rekening tujuan</h2>
                @foreach ($page->setting('catalog.payment.banks', []) as $bank)
                    <div class="bank-row d-flex align-items-center justify-content-between gap-3 p-3 {{ $loop->last ? '' : 'mb-2' }}">
                        <div>
                            <div class="small text-muted">{{ $bank['bank'] }} · a.n. {{ $bank['holder'] }}</div>
                            <div class="fw-semibold fs-5">{{ $bank['number'] }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $bank['number'] }}')">Salin</button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card order-card shadow-sm">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Rincian pesanan</h2>
            @foreach ($order->items as $item)
                <div class="d-flex justify-content-between gap-3 small mb-2">
                    <span>{{ $item->name }} <span class="text-muted">× {{ $item->quantity }}</span></span>
                    <span class="text-nowrap">{{ $rupiah($item->subtotal) }}</span>
                </div>
            @endforeach
            <div class="d-flex justify-content-between border-top pt-2 mt-2 fw-semibold">
                <span>Total</span><span>{{ $rupiah($order->total) }}</span>
            </div>
            <div class="small text-muted mt-3">Pembayaran: {{ $order->paymentLabel() }}</div>
        </div>
    </div>
@endsection
