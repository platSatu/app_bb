@extends('page.public.layout', ['open' => false])

@section('title', 'E-ticket '.$ticket->number)
@section('width', '920px')

@push('head')
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    @include('page.public._eticket-card', [
        'background' => $background,
        'eventName' => $page->name,
        'eventDate' => $page->start_date?->translatedFormat('D, d M Y'),
        'category' => $ticket->category_name,
        'holder' => $ticket->holder_name,
        'booking' => $ticket->booking_code,
        'number' => $ticket->number,
        'sequence' => $ticket->sequence.' dari '.$ticket->order->tickets()->count(),
        'qr' => $qr,
        'color' => ($page->setting('ticket.color') ?: '#e36666'),
        'place' => $page->branch?->name,
        'used' => $ticket->isCheckedIn(),
    ])

    @if (\App\Services\Ticket\TicketCardService::enabled($page))
        <div class="alert {{ $ticket->hasCard() ? 'alert-success' : 'alert-info' }} text-center mt-3 mx-auto" style="max-width: 880px;">
            @if ($ticket->hasCard())
                <i class="ri-id-card-line me-1"></i>E-ticket ini sudah ditukar jadi ID card. Untuk absen di setiap kelas, pakai QR di ID card ya.
            @else
                <i class="ri-id-card-line me-1"></i>Sesampainya di lokasi, tukarkan e-ticket ini jadi ID card di kiosk. Cukup scan QR di atas, kartunya langsung tercetak.
            @endif
        </div>
    @endif

    @if ($ticket->isCheckedIn())
        <div class="alert alert-success text-center mt-3 mx-auto" style="max-width: 880px;">Tiket ini sudah dipakai untuk registrasi ulang pada {{ $ticket->checked_in_at->translatedFormat('d M Y H:i') }}.</div>
    @endif

    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
        <a href="{{ route('eticket.pdf', [$page->slug, $ticket->number, request()->route('token')]) }}" class="btn btn-primary"><i class="ri-download-2-line me-1"></i>Download PDF</a>
        <button type="button" class="btn btn-outline-secondary" id="share-ticket"><i class="ri-share-line me-1"></i>Bagikan</button>
        <a href="{{ route('eticket.order', [$page->slug, $ticket->order->access_token]) }}" class="btn btn-light">Semua tiket</a>
    </div>
    <p class="text-center small text-muted mt-3 mb-0">Tunjukkan QR ini saat registrasi ulang. Jangan bagikan ke orang yang tidak berkepentingan.</p>

    @push('scripts')
        <script>
            document.getElementById('share-ticket').addEventListener('click', function () {
                var data = { title: @json('E-ticket '.$page->name), text: @json($ticket->category_name.' · Kode booking '.$ticket->booking_code), url: location.href };
                if (navigator.share) { navigator.share(data).catch(function () {}); return; }
                navigator.clipboard.writeText(location.href).then(() => { this.innerHTML = '<i class="ri-check-line me-1"></i>Link disalin'; });
            });
        </script>
    @endpush
@endsection
