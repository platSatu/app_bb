<x-mail::message>
# Pembayaran berhasil 🎉

Halo **{{ $order->name }}**, terima kasih! Pembayaran untuk **{{ $order->page->name }}** sudah kami terima.

Nomor pesanan: **{{ $order->reference }}** · Total **Rp {{ number_format($order->total, 0, ',', '.') }}**

<x-mail::table>
| Tiket | Kategori | Kode Booking |
|:--|:--|:--|
@foreach ($order->tickets as $ticket)
| {{ $ticket->sequence }} dari {{ $order->tickets->count() }} · [{{ $ticket->number }}]({{ $ticket->url($order->page->slug) }}) | {{ $ticket->category_name }} | {{ $ticket->booking_code }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$order->url()">
Lihat Semua E-ticket
</x-mail::button>

Tunjukkan QR code e-ticket saat registrasi ulang. Setiap tiket punya QR sendiri, jadi bisa dibagikan ke teman satu per satu.

Jangan bagikan link e-ticket ke orang yang tidak berkepentingan, karena siapa pun yang memegang link bisa memakai tiketnya.

Salam,<br>
{{ $order->page->branch?->company?->name ?? config('app.name') }}
</x-mail::message>
