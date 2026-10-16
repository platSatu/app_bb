@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
<x-mail::message>
@if ($kind === 'paid')
# Pembayaran diterima 🎉

Halo **{{ $invoice->customer_name }}**, terima kasih! Pembayaran invoice **{{ $invoice->number }}** sudah kami terima.

<x-mail::table>
| | |
|:--|--:|
| {{ $invoice->description }} | {{ $rp($invoice->amount) }} |
@if ($invoice->late_fee > 0)
| Denda keterlambatan | {{ $rp($invoice->late_fee) }} |
@endif
| **Total dibayar** | **{{ $rp($invoice->paid_amount) }}** |
</x-mail::table>

Dibayar pada {{ $invoice->paid_at?->translatedFormat('d F Y H:i') }}. Simpan email ini sebagai bukti pembayaran.
@else
# {{ $kind === 'reminder' ? 'Pengingat tagihan' : 'Tagihan baru untuk Anda' }}

Halo **{{ $invoice->customer_name }}**,
@if ($kind === 'reminder')
ini pengingat ramah untuk tagihan yang belum dibayar:
@else
ada tagihan baru dari **{{ $invoice->company?->name }}**:
@endif

<x-mail::table>
| | |
|:--|--:|
| Nomor | {{ $invoice->number }} |
| {{ $invoice->description }} | {{ $rp($invoice->amount) }} |
@if ($invoice->due_date)
| Jatuh tempo | {{ $invoice->due_date->translatedFormat('d F Y') }} |
@endif
</x-mail::table>

@if ($invoice->apply_late_fee && $invoice->category?->hasLateFee())
Pembayaran setelah jatuh tempo dikenakan denda ({{ $invoice->category->lateFeeSummary() }}).
@endif

<x-mail::button :url="$invoice->payUrl()">
Bayar Sekarang
</x-mail::button>

Bisa dibayar lewat transfer virtual account, QRIS, atau e-wallet. Abaikan email ini kalau sudah membayar.
@endif

Salam,<br>
{{ $invoice->company?->name ?? config('app.name') }}
</x-mail::message>
