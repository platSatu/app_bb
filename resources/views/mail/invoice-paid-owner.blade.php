@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))
<x-mail::message>
@if ($kind === 'refund')
# Pembayaran perlu dikembalikan

Customer **{{ $invoice->customer_name }}** membayar **{{ $rp($amount) }}** untuk invoice **{{ $invoice->number }}**, padahal invoice ini sudah lunas atau dibatalkan lebih dulu.

Dananya sudah masuk ke saldo Anda. Mohon kembalikan ke customer.
@else
# Invoice lunas

Invoice **{{ $invoice->number }}** dari **{{ $invoice->customer_name }}** sudah dibayar.

<x-mail::table>
| | |
|:--|--:|
| {{ $invoice->description }} | {{ $rp($invoice->amount) }} |
@if ($invoice->late_fee > 0)
| Denda keterlambatan | {{ $rp($invoice->late_fee) }} |
@endif
| Dibayar customer | {{ $rp($invoice->paid_amount) }} |
| Biaya layanan bizbos | − {{ $rp($invoice->fee) }} |
| **Masuk ke saldo** | **{{ $rp($invoice->net_amount) }}** |
</x-mail::table>

Dibayar {{ $invoice->paid_at?->translatedFormat('d F Y H:i') }}{{ $invoice->payment_method ? ' lewat '.$invoice->payment_method : '' }} · {{ $invoice->company?->name }} · {{ $invoice->branch?->name }}
@endif

<x-mail::button :url="route('invoices.show', $invoice)">
Lihat Detail Invoice
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
