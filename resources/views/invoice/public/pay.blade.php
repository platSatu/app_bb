@php
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $company = $invoice->company;
    $lateFee = $invoice->currentLateFee();
    $final = ! $invoice->isUnpaid();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Invoice {{ $invoice->number }} · {{ $company?->name }}</title>
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link href="{{ asset('be') }}/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4" style="max-width: 520px;">
    <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
        @if ($company?->logoUrl())
            <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" height="40" style="object-fit: contain;">
        @endif
        <span class="fw-semibold">{{ $company?->name }}</span>
    </div>

    @if (session('error'))
        <div class="alert alert-danger"><i class="ri-error-warning-line me-1"></i>{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                @switch($invoice->status)
                    @case('paid')
                        <i class="ri-checkbox-circle-line fs-1 text-success"></i>
                        <h1 class="h5 mt-1 mb-0">Pembayaran berhasil, terima kasih!</h1>
                        <div class="text-muted small">Dibayar {{ $invoice->paid_at?->translatedFormat('d F Y H:i') }}</div>
                        @break
                    @case('cancelled')
                        <i class="ri-close-circle-line fs-1 text-danger"></i>
                        <h1 class="h5 mt-1 mb-0">Invoice ini sudah dibatalkan</h1>
                        <div class="text-muted small">Tidak perlu dibayar. Hubungi penagih kalau ada pertanyaan.</div>
                        @break
                    @case('expired')
                        <i class="ri-timer-flash-line fs-1 text-muted"></i>
                        <h1 class="h5 mt-1 mb-0">Batas waktu pembayaran sudah lewat</h1>
                        <div class="text-muted small">Silakan minta invoice baru ke penagih.</div>
                        @break
                    @default
                        <div class="text-muted small">Total yang harus dibayar</div>
                        <div class="fs-2 fw-bold">{{ $rp($invoice->totalDue()) }}</div>
                        @if ($invoice->isOverdue())
                            <span class="badge bg-danger-subtle text-danger">Lewat jatuh tempo</span>
                        @elseif ($invoice->due_date)
                            <span class="badge bg-light text-dark border">Jatuh tempo {{ $invoice->due_date->translatedFormat('d F Y') }}</span>
                        @endif
                @endswitch
            </div>

            <table class="table table-sm mb-3">
                <tr><td class="text-muted">Nomor</td><td class="text-end fw-semibold">{{ $invoice->number }}</td></tr>
                <tr><td class="text-muted">Untuk</td><td class="text-end">{{ $invoice->customer_name }}</td></tr>
                <tr><td class="text-muted">{{ $invoice->description }}</td><td class="text-end">{{ $rp($invoice->amount) }}</td></tr>
                @if ($lateFee > 0)
                    <tr><td class="text-muted">Denda keterlambatan</td><td class="text-end">{{ $rp($lateFee) }}</td></tr>
                @endif
                <tr class="fw-bold"><td>Total</td><td class="text-end">{{ $rp($invoice->isPaid() ? $invoice->paid_amount : $invoice->totalDue()) }}</td></tr>
            </table>

            @if ($invoice->isUnpaid())
                <form method="POST" action="{{ route('invoice.pay.checkout', array_filter(['token' => $invoice->token, 'origin' => $origin])) }}" onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="ri-secure-payment-line me-1"></i>Bayar Sekarang</button>
                </form>
                <div class="small text-muted text-center mt-2">
                    Virtual account, QRIS, atau e-wallet.
                    @if ($invoice->apply_late_fee && $invoice->category?->hasLateFee())
                        Denda setelah jatuh tempo: {{ $invoice->category->lateFeeSummary() }}.
                    @endif
                </div>
                @if ($invoice->payments()->where('status', 'pending')->exists())
                    <div class="text-center mt-3">
                        <a href="{{ route('invoice.pay', array_filter(['token' => $invoice->token, 'returned' => 1, 'popup' => $origin ? 1 : null, 'origin' => $origin])) }}" class="small">Sudah bayar? Cek status pembayaran</a>
                    </div>
                @endif
            @elseif ($invoice->isPaid() && $invoice->return_url && ! $origin)
                <a href="{{ $invoice->return_url }}" class="btn btn-outline-primary w-100" data-return>Kembali ke {{ parse_url($invoice->return_url, PHP_URL_HOST) }}</a>
            @endif
        </div>
    </div>

    <p class="text-center small text-muted mt-3 mb-0"><i class="ri-lock-line"></i> Pembayaran aman diproses oleh bizbos & Duitku.</p>
</main>

@if ($origin || ($invoice->isUnpaid() && request()->boolean('returned')))
    <script>
        (function () {
            var origin = @json($origin), status = @json($invoice->status), final = @json($final);
            var payload = { source: 'bizbos-pay', status: status, invoice_id: @json($invoice->id), external_id: @json($invoice->external_id), number: @json($invoice->number) };

            // Kabar ke website yang membuka popup; hanya ke origin terdaftar.
            if (origin && final && window.opener) {
                window.opener.postMessage(payload, origin);
                if (status === 'paid') setTimeout(function () { window.close(); }, 2500);
            }

            // Baru kembali dari Duitku tapi belum terkonfirmasi: cek berkala sampai 5 menit.
            if (!final && @json(request()->boolean('returned'))) {
                var tries = 0, timer = setInterval(function () {
                    if (++tries > 60) return clearInterval(timer);
                    fetch({{ \Illuminate\Support\Js::from(route('invoice.pay.status', $invoice->token)) }}, { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (data) { if (data.status !== 'unpaid') location.reload(); });
                }, 5000);
            }
        })();
    </script>
@endif
</body>
</html>
