<x-mail::message>
# {{ $days === 0 ? 'Paket Anda berakhir hari ini' : ($days === 1 ? 'Paket Anda berakhir besok' : "Paket Anda berakhir {$days} hari lagi") }}

Halo **{{ $user->name }}**,

@if ($days === 0)
masa aktif layanan berikut berakhir hari ini. Setelah lewat, menunya tertutup sementara dan pembayaran online untuk customer Anda ikut berhenti. Data Anda tetap aman dan langsung bisa dipakai lagi begitu diperpanjang.
@else
kami mau mengingatkan, masa aktif layanan berikut akan segera berakhir. Perpanjang dari sekarang supaya pekerjaan Anda dan customer tidak terganggu.
@endif

<x-mail::table>
| Layanan | Cabang | Berakhir |
|:--|:--|:--|
@foreach ($items as $item)
| {{ $item->appLabel() }} | {{ $item->branch?->name }} | {{ $item->ends_at->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('profile.packages')">
Perpanjang Paket
</x-mail::button>

Kalau sudah diperpanjang, abaikan saja email ini.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
