@extends('layouts.dashboard')

@section('title', 'Invoice '.$invoice->number)
@section('page-section', $isAdmin ? 'Superadmin' : 'Invoice')

@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))

@section('content')
    @include('invoice._tabs')

    <div class="mb-3"><a href="{{ route($routes.'.index') }}" class="text-muted"><i class="ri-arrow-left-line"></i> Semua invoice</a></div>

    @if ($payments->where('status', 'refund_needed')->isNotEmpty())
        <div class="alert alert-danger">
            <i class="ri-error-warning-line me-1"></i>Ada pembayaran ganda / pembayaran setelah invoice dibatalkan. Dananya sudah masuk ke saldo, mohon kembalikan ke customer.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <span class="badge {{ $invoice->statusBadge() }} mb-2">{{ $invoice->statusLabel() }}</span>
                    <h5 class="mb-1">{{ $invoice->number }}</h5>
                    <div class="text-muted mb-3">{{ $invoice->description }}</div>
                    <dl class="row small mb-0">
                        <dt class="col-5">Customer</dt><dd class="col-7">{{ $invoice->customer_name }}<div class="text-muted">{{ $invoice->customer_email }} {{ $invoice->customer_phone ? '+'.$invoice->customer_phone : '' }}</div></dd>
                        @if ($isAdmin) <dt class="col-5">Perusahaan</dt><dd class="col-7">{{ $invoice->branch?->company?->name }} · {{ $invoice->branch?->name }}</dd> @endif
                        <dt class="col-5">Sumber</dt><dd class="col-7">{{ $invoice->sourceLabel() }}{{ $invoice->external_id ? ' · '.$invoice->external_id : '' }}</dd>
                        @if ($invoice->batch) <dt class="col-5">Periode</dt><dd class="col-7"><a href="{{ $isAdmin ? '#' : route('invoices.batches.show', $invoice->batch) }}">{{ $invoice->batch->name }}</a></dd> @endif
                        <dt class="col-5">Tagihan</dt><dd class="col-7">{{ $rp($invoice->amount) }}</dd>
                        <dt class="col-5">Denda</dt><dd class="col-7">{{ $rp($invoice->currentLateFee()) }}{{ $invoice->isUnpaid() && $invoice->apply_late_fee ? ' (per hari ini)' : '' }}</dd>
                        @if ($invoice->due_date) <dt class="col-5">Jatuh tempo</dt><dd class="col-7 {{ $invoice->isOverdue() ? 'text-danger' : '' }}">{{ $invoice->due_date->translatedFormat('d F Y') }}</dd> @endif
                        @if ($invoice->expires_at) <dt class="col-5">Berlaku s.d.</dt><dd class="col-7">{{ $invoice->expires_at->translatedFormat('d F Y H:i') }}</dd> @endif
                        @if ($invoice->isPaid())
                            <dt class="col-5">Dibayar</dt><dd class="col-7">{{ $rp($invoice->paid_amount) }} · {{ $invoice->paid_at?->translatedFormat('d M Y H:i') }} {{ $invoice->payment_method ? '('.$invoice->payment_method.')' : '' }}</dd>
                            <dt class="col-5">Biaya layanan</dt><dd class="col-7">− {{ $rp($invoice->fee) }}</dd>
                            <dt class="col-5">Masuk saldo</dt><dd class="col-7 fw-semibold text-success">{{ $rp($invoice->net_amount) }}</dd>
                        @endif
                        @if ($invoice->cancel_reason) <dt class="col-5">Alasan batal</dt><dd class="col-7">{{ $invoice->cancel_reason }}</dd> @endif
                        @if ($invoice->emailed_at) <dt class="col-5">Email terkirim</dt><dd class="col-7">{{ $invoice->emailed_at->translatedFormat('d M Y H:i') }}</dd> @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if (! $isAdmin && $invoice->isUnpaid())
                <div class="card mb-3">
                    <div class="card-body">
                        <h6>Link Bayar</h6>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" value="{{ $invoice->payUrl() }}" readonly>
                            <button class="btn btn-outline-primary" type="button" data-copy="{{ $invoice->payUrl() }}"><i class="ri-file-copy-line"></i> Salin</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $invoice->payUrl() }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ri-external-link-line"></i> Buka</a>
                            <form method="POST" action="{{ route('invoices.resend', $invoice) }}">@csrf<button class="btn btn-sm btn-light" @disabled(! $invoice->customer_email)><i class="ri-mail-send-line"></i> Kirim Email</button></form>
                            <form method="POST" action="{{ route('invoices.regenerate', $invoice) }}" onsubmit="return confirm('Buat link baru? Link lama langsung tidak bisa dipakai.')">@csrf<button class="btn btn-sm btn-light"><i class="ri-refresh-line"></i> Link Baru</button></form>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body">
                        <h6>Batalkan Invoice</h6>
                        <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="d-flex gap-2" onsubmit="return confirm('Batalkan invoice ini?')">
                            @csrf
                            <input type="text" name="reason" maxlength="250" class="form-control" placeholder="Alasan pembatalan" required>
                            <button class="btn btn-outline-danger text-nowrap">Batalkan</button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <h6>Riwayat Percobaan Bayar</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Order Duitku</th><th class="text-end">Nominal</th><th>Status</th><th>Waktu</th></tr></thead>
                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="small">{{ $payment->merchant_order_id }}</td>
                                        <td class="text-end small">{{ $rp($payment->amount) }}</td>
                                        <td><span class="badge {{ $payment->status === 'refund_needed' ? 'bg-danger-subtle text-danger' : 'bg-light text-dark border' }}">{{ $payment->statusLabel() }}</span></td>
                                        <td class="small text-muted">{{ $payment->created_at->translatedFormat('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted small">Customer belum menekan tombol bayar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('invoice.invoices._callbacks')

    @include('invoice.invoices._table-script')
@endsection
