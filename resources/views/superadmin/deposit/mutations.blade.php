@extends('layouts.dashboard')

@section('title', 'Mutasi')
@section('page-section', 'Superadmin')

@php
    $gateway = \App\Models\PaymentGatewaySetting::duitku();
    $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $from = $filters['from']?->translatedFormat('d M Y');
    $until = $filters['until']?->translatedFormat('d M Y');
    $period = match (true) {
        $from && $until => $from.' – '.$until,
        (bool) $from => 'Sejak '.$from,
        (bool) $until => 'Sampai '.$until,
        default => 'Semua waktu',
    };
    $narrowed = filled($filters['source']) || filled($filters['direction']) || filled($filters['search']);
    $flags = ['refund' => ['Perlu refund', 'Uang sudah masuk Duitku tapi tagihan/tiket tidak bisa diproses. Kembalikan ke pembayar.'],
              'check' => ['Cek stok', 'Dibayar setelah batas waktu. Lihat catatan pesanan.']];
    $reviewCount = $summary['review']['withdrawal'] + $summary['review']['disbursement'];
@endphp

@push('styles')
    <style>
        .mutasi { font-size: .875rem; }
        .mutasi th { font-weight: 600; white-space: nowrap; vertical-align: bottom; }
        .mutasi th small { display: block; font-weight: 400; font-size: .72rem; color: var(--bs-secondary-color, #6c757d); }
        .mutasi td { vertical-align: middle; padding-top: .65rem; padding-bottom: .65rem; }
        .mutasi .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); line-height: 1.35; margin-top: 2px; }
        .mutasi .clip { display: block; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mutasi .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .mutasi .nil { color: var(--bs-tertiary-color, #adb5bd); }
        .mutasi .credit { color: #16a34a; font-weight: 600; }
        .mutasi .debit { color: #dc2626; font-weight: 600; }
        .mutasi-stat .label { font-size: .78rem; color: var(--bs-secondary-color, #6c757d); }
        .mutasi-stat .value { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .mutasi-src { font-size: .8rem; }
        .mutasi-src a { text-decoration: none; }
    </style>
@endpush

@section('content')
    {{-- Ringkasan untuk filter yang sama dengan tabel. --}}
    <div class="row g-3 mb-3">
        @foreach ([
            ['Kredit · uang masuk', $rp($summary['credit']), 'credit', $summary['credit_count'].' pembayaran · '.$period],
            ['Debit · uang keluar', $rp($summary['debit']), 'debit', $summary['debit_count'].' transfer · '.$period],
            ['Saldo tercatat', $rp($summary['balance']), '', ($until ? 'Per '.$until : 'Saat ini').' · kredit − debit semua sumber'],
            ['Pendapatan fee bizbos', $rp($summary['fee']), '', 'Fee invoice, tiket, tarik saldo & disbursement · '.$period],
        ] as [$label, $value, $tone, $note])
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 mb-0 mutasi-stat">
                    <div class="card-body">
                        <div class="label">{{ $label }}</div>
                        <div class="value {{ $tone === 'credit' ? 'text-success' : ($tone === 'debit' ? 'text-danger' : '') }}">{{ $value }}</div>
                        <div class="small text-muted">{{ $note }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3 mutasi-src">
            <span class="text-muted">Per sumber:</span>
            @foreach (\App\Services\Deposit\MutationLedger::SOURCES as $key => [$label, $direction])
                <a href="{{ route('superadmin.mutations.index', array_filter([...request()->only(['date_from', 'date_to']), 'source' => $key])) }}"
                   class="{{ $filters['source'] === $key ? 'fw-semibold' : 'text-body' }}">
                    {{ $label }}
                    <span class="{{ $direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $direction === 'credit' ? '+' : '−' }}{{ $rp($summary['per_source'][$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    @if ($summary['refund_count'] > 0 || $reviewCount > 0)
        <div class="alert alert-warning py-2 small">
            <i class="ri-error-warning-line me-1"></i>
            @if ($summary['refund_count'] > 0)
                {{ $summary['refund_count'] }} pembayaran ({{ $rp($summary['refund']) }}) <b>perlu refund</b>: uangnya sudah masuk Duitku tetapi tidak masuk ke saldo siapa pun.
            @endif
            @if ($reviewCount > 0)
                {{ $reviewCount }} transfer berstatus <b>Sedang Dicek Admin</b> belum dihitung sebagai Debit —
                <a href="{{ route('superadmin.withdrawals.index') }}">cek tarik saldo</a> / <a href="{{ route('superadmin.payouts.index') }}">disbursement</a>.
            @endif
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-4 col-xl-3">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="No. referensi / nama / customer" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small text-muted mb-1">Sumber</label>
                    <select name="source" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Services\Deposit\MutationLedger::SOURCES as $key => [$label])
                            <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label class="form-label small text-muted mb-1">Arah</label>
                    <select name="direction" class="form-select">
                        <option value="">Kredit & Debit</option>
                        <option value="credit" @selected($filters['direction'] === 'credit')>Kredit (masuk)</option>
                        <option value="debit" @selected($filters['direction'] === 'debit')>Debit (keluar)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-6 col-xl-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Filter"><i class="ri-search-line"></i></button>
                    @if (request()->hasAny(['search', 'source', 'direction', 'date_from', 'date_to']))
                        <a href="{{ route('superadmin.mutations.index') }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover mb-0 mutasi">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Sumber</th>
                            <th>Pihak</th>
                            <th class="num">Kredit<small>uang masuk</small></th>
                            <th class="num">Debit<small>uang keluar</small></th>
                            <th class="num">Saldo Awal</th>
                            <th class="num">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php($at = \Illuminate\Support\Carbon::parse($row->at))
                            <tr>
                                <td class="text-nowrap">
                                    {{ $at->translatedFormat('d M Y') }}
                                    <span class="sub">{{ $at->format('H:i') }}</span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold">{{ \App\Services\Deposit\MutationLedger::label($row->source) }}</span>
                                    <span class="sub">
                                        {{ $row->reference }}
                                        @if ($row->method) · {{ $row->direction === 'credit' ? $gateway->methodName($row->method) : $row->method }} @endif
                                        @if ($row->flag && isset($flags[$row->flag]))
                                            · <span class="text-warning-emphasis fw-semibold" title="{{ $flags[$row->flag][1] }}">{{ $flags[$row->flag][0] }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="clip" title="{{ $row->party }}">{{ $row->party ?: '-' }}</span>
                                    @if ($row->party_note) <span class="sub clip" title="{{ $row->party_note }}">{{ $row->party_note }}</span> @endif
                                </td>
                                <td class="num">
                                    @if ($row->direction === 'credit')
                                        <span class="credit">+{{ $rp($row->amount) }}</span>
                                        @if ($row->fee > 0) <span class="sub">fee {{ $rp($row->fee) }}</span> @endif
                                    @else
                                        <span class="nil">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($row->direction === 'debit')
                                        <span class="debit">−{{ $rp($row->amount) }}</span>
                                        @if ($row->fee > 0) <span class="sub">fee {{ $rp($row->fee) }}</span> @endif
                                    @else
                                        <span class="nil">—</span>
                                    @endif
                                </td>
                                <td class="num text-muted">{{ isset($row->balance_before) ? $rp($row->balance_before) : '—' }}</td>
                                <td class="num fw-semibold">{{ isset($row->balance_after) ? $rp($row->balance_after) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada mutasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="small text-muted">
                    @if ($narrowed)
                        Saldo per baris disembunyikan saat memakai filter sumber/arah/cari.
                    @endif
                    Saldo tercatat belum dikurangi potongan Duitku (MDR & biaya transfer); bandingkan dengan saldo asli di dashboard Duitku.
                    Total saldo user saat ini: {{ $rp($summary['user_balance']) }}.
                </div>
                {{ $rows->links() }}
            </div>
        </div>
    </div>
@endsection
