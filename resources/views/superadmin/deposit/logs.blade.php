@extends('layouts.dashboard')

@section('title', 'Sett & Lap')
@section('page-section', 'Superadmin')

@php
    $gateway = \App\Models\PaymentGatewaySetting::duitku();
    $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    // Aplikasi dari awalan nomor order, sama dengan pembagian di DuitkuCallbackService.
    $app = fn (?string $order) => match (true) {
        str_starts_with((string) $order, 'TIX') => 'Ticketing',
        str_starts_with((string) $order, 'ORD') => 'Toko Online',
        str_starts_with((string) $order, 'INV') => 'Invoice',
        str_starts_with((string) $order, 'TGH') => 'Bayar Beberapa Tagihan',
        default => 'Top Up Saldo',
    };
@endphp

@push('styles')
    <style>
        .log-table { font-size: .875rem; }
        .log-table th { font-weight: 600; white-space: nowrap; }
        .log-table td { vertical-align: middle; }
        .log-table .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); margin-top: 2px; }
        .log-table .clip { display: block; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>
@endpush

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'logs'])

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills mb-3">
                @foreach (['callback' => ['Callback Duitku', 'kabar pembayaran dari Duitku ke bizbos'], 'webhook' => ['Webhook ke Aplikasi Pelanggan', 'kabar dari bizbos ke aplikasi pelanggan (API)']] as $key => [$label, $hint])
                    <li class="nav-item">
                        <a class="nav-link {{ $type === $key ? 'active' : '' }}" href="{{ route('superadmin.mutations.logs', ['type' => $key]) }}" title="{{ $hint }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            <form method="GET" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="60" value="{{ request('search') }}"
                        placeholder="{{ $type === 'callback' ? 'Nomor order, mis. INV… / TIX…' : 'Nomor invoice, ref API, ref disbursement, pelanggan' }}">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Hasil</label>
                    <select name="result" class="form-select">
                        <option value="">Semua</option>
                        <option value="ok" @selected(request('result') === 'ok')>Berhasil</option>
                        <option value="problem" @selected(request('result') === 'problem')>Bermasalah</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Filter"><i class="ri-search-line"></i></button>
                    @if (request()->hasAny(['search', 'result', 'date_from', 'date_to']))
                        <a href="{{ route('superadmin.mutations.logs', ['type' => $type]) }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover mb-0 log-table">
                    <thead class="table-light">
                        @if ($type === 'callback')
                            <tr><th>Waktu</th><th>Nomor Order</th><th>Metode</th><th class="text-end">Nominal</th><th>Hasil</th><th>Keterangan</th></tr>
                        @else
                            <tr><th>Waktu</th><th>Pelanggan</th><th>Kabar</th><th>Untuk</th><th>Percobaan</th><th>Hasil</th></tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php($at = \Illuminate\Support\Carbon::parse($log->created_at))
                            <tr>
                                <td class="text-nowrap">{{ $at->translatedFormat('d M Y') }}<span class="sub">{{ $at->format('H:i:s') }}</span></td>
                                @if ($type === 'callback')
                                    <td class="text-nowrap">
                                        <span class="fw-semibold">{{ $log->merchant_order_id ?: '-' }}</span>
                                        <span class="sub">{{ $app($log->merchant_order_id) }}</span>
                                    </td>
                                    <td class="text-nowrap">{{ $gateway->methodName($log->payload['paymentCode'] ?? null) ?? '-' }}</td>
                                    <td class="text-end text-nowrap">{{ $rp($log->amount()) }}</td>
                                    <td>
                                        <span class="badge {{ $log->resultBadge() }}">{{ $log->resultLabel() }}</span>
                                        @unless ($log->signature_valid) <span class="sub text-danger">Tanda tangan tidak valid</span> @endunless
                                    </td>
                                    <td><span class="clip text-muted" title="{{ $log->message }}">{{ $log->message ?: '-' }}</span><span class="sub">IP {{ $log->ip_address ?: '-' }}</span></td>
                                @else
                                    <td>{{ $log->company ?: '-' }}<span class="sub">{{ parse_url((string) $log->webhook_url, PHP_URL_HOST) ?: '-' }}</span></td>
                                    <td class="text-nowrap"><code>{{ $log->event }}</code></td>
                                    <td class="text-nowrap">
                                        {{ $log->invoice_number ?: ($log->payout_reference ?: '-') }}
                                        @if ($log->external_id) <span class="sub">Ref {{ $log->external_id }}</span> @endif
                                    </td>
                                    <td>ke-{{ $log->attempt }}</td>
                                    <td>
                                        @php($ok = $log->http_status >= 200 && $log->http_status < 300)
                                        <span class="badge {{ $ok ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $log->http_status ? 'HTTP '.$log->http_status : 'Tidak terhubung' }}</span>
                                        @if (! $ok && $log->error) <span class="sub clip" title="{{ $log->error }}">{{ $log->error }}</span> @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $logs->links() }}</div>

            <div class="small text-muted mt-2">
                {{ $type === 'callback'
                    ? 'Setiap kabar dari Duitku tercatat, termasuk yang ditolak. "Sudah diproses sebelumnya" = kiriman ulang Duitku, aman.'
                    : 'Webhook gagal dikirim ulang otomatis (1 menit, 5 menit, 30 menit, …). Isi kabar & balasan server pelanggan tidak ditampilkan.' }}
            </div>
        </div>
    </div>
@endsection
