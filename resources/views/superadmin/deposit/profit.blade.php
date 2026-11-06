@extends('layouts.dashboard')

@section('title', 'Sett & Lap')
@section('page-section', 'Superadmin')

@php
    use App\Services\Deposit\MutationLedger;

    $rp = fn ($v) => ((int) $v < 0 ? '−' : '').'Rp '.number_format(abs((int) $v), 0, ',', '.');
    $period = match (true) {
        $from && $until => $from->translatedFormat('d M Y').' – '.$until->translatedFormat('d M Y'),
        (bool) $from => 'Sejak '.$from->translatedFormat('d M Y'),
        (bool) $until => 'Sampai '.$until->translatedFormat('d M Y'),
        default => 'Semua waktu',
    };
    $total = fn (string $field) => (int) $apps->sum($field);
    $liabilities = $userBalance + $refund;
    $equity = isset($duitku['balance']) ? $duitku['balance'] - $liabilities : null;
@endphp

@push('styles')
    <style>
        .laba { font-size: .875rem; }
        .laba th { font-weight: 600; white-space: nowrap; vertical-align: bottom; }
        .laba th small, .laba .sub { display: block; font-weight: 400; font-size: .75rem; color: var(--bs-secondary-color, #6c757d); }
        .laba .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .laba .nil { color: var(--bs-tertiary-color, #adb5bd); }
        .laba tfoot td { font-weight: 700; border-top: 2px solid var(--bs-border-color, #dee2e6); }
        .neraca .value { font-size: 1.2rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    </style>
@endpush

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'profit'])

    {{-- Neraca: posisi saat ini (tidak terpengaruh filter tanggal). --}}
    <div class="card neraca">
        <div class="card-body">
            <h6 class="mb-3">Neraca · posisi saat ini</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-muted">Aset: saldo Duitku {{ $duitku['sandbox'] ? '(sandbox)' : '' }}</div>
                    @isset($duitku['balance'])
                        <div class="value">{{ $rp($duitku['balance']) }}</div>
                        <div class="small text-muted">Efektif {{ $rp($duitku['effective']) }}</div>
                    @else
                        <div class="value text-muted">—</div>
                        <div class="small text-danger">{{ $duitku['error'] }}</div>
                    @endisset
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Kewajiban: uang milik pelanggan</div>
                    <div class="value">{{ $rp($liabilities) }}</div>
                    <div class="small text-muted">Saldo pelanggan {{ $rp($userBalance) }} + perlu refund {{ $rp($refund) }}</div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted">Selisih: milik bizbos</div>
                    <div class="value {{ $equity !== null && $equity < 0 ? 'text-danger' : '' }}">{{ $equity === null ? '—' : $rp($equity) }}</div>
                    <div class="small {{ $equity !== null && $equity < 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $equity !== null && $equity < 0 ? 'Uang di Duitku kurang dari uang milik pelanggan. Segera cek.' : 'Aset − kewajiban' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Pelanggan</label>
                    <select name="customer" class="form-select">
                        <option value="">Semua pelanggan</option>
                        @foreach ($customerOptions as $option)
                            <option value="{{ $option->id }}" @selected(request('customer') === $option->id)>{{ $option->company?->name ?: $option->name }} — {{ $option->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Aplikasi</label>
                    <select name="app" class="form-select">
                        <option value="">Semua aplikasi</option>
                        @foreach ($appOptions as $key => $label)
                            <option value="{{ $key }}" @selected(request('app') === $key)>{{ $label }}</option>
                        @endforeach
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
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line"></i></button>
                    @if (request()->hasAny(['date_from', 'date_to', 'customer', 'app']))
                        <a href="{{ route('superadmin.mutations.profit') }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <h6 class="mb-2">Laba per aplikasi <span class="small text-muted fw-normal">· {{ $period }}</span></h6>
            <div class="table-responsive mb-4">
                <table class="table table-hover mb-0 laba">
                    <thead class="table-light">
                        <tr>
                            <th>Aplikasi</th>
                            <th class="num">Transaksi</th>
                            <th class="num">Nilai<small>dibayar / ditarik</small></th>
                            <th class="num">Fee layanan<small>invoice, tiket, transfer</small></th>
                            <th class="num">Fee payment gateway<small>dari pelanggan</small></th>
                            <th class="num">Biaya Duitku<small>QRIS & transfer</small></th>
                            <th class="num">Laba</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Aplikasi transaksi, lalu sewa paket per aplikasi (yang ada di periode ini), bonus referral paling akhir. --}}
                        @foreach (request('app') ? [request('app')] : [...array_keys(array_diff_key(MutationLedger::APPS, ['referral' => 1])), ...$apps->keys()->filter(fn ($k) => str_starts_with($k, 'sewa:'))->sort()->values(), 'referral'] as $key)
                            @php($label = MutationLedger::appLabel($key))
                            @php($row = $apps->get($key))
                            @php($profit = (int) ($row->profit ?? 0))
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ number_format((int) ($row->trx ?? 0), 0, ',', '.') }}</td>
                                <td class="num">{{ $row ? $rp($row->gross) : '—' }}</td>
                                <td class="num">{!! $row && $row->service ? e($rp($row->service)) : '<span class="nil">—</span>' !!}</td>
                                <td class="num">{!! $row && $row->gateway ? e($rp($row->gateway)) : '<span class="nil">—</span>' !!}</td>
                                <td class="num text-danger">{{ $row && $row->duitku ? $rp(-$row->duitku) : '' }}</td>
                                <td class="num fw-semibold {{ $profit < 0 ? 'text-danger' : ($profit > 0 ? 'text-success' : '') }}">{{ $rp($profit) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Laba bersih</td>
                            <td class="num">{{ number_format($total('trx'), 0, ',', '.') }}</td>
                            <td class="num"></td>
                            <td class="num">{{ $rp($total('service')) }}</td>
                            <td class="num">{{ $rp($total('gateway')) }}</td>
                            <td class="num text-danger">{{ $rp(-$total('duitku')) }}</td>
                            <td class="num {{ $total('profit') < 0 ? 'text-danger' : 'text-success' }}">{{ $rp($total('profit')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h6 class="mb-2">Laba per pelanggan <span class="small text-muted fw-normal">· 50 teratas</span></h6>
            <div class="table-responsive">
                <table class="table table-hover mb-0 laba">
                    <thead class="table-light">
                        <tr>
                            <th>Pelanggan</th>
                            <th class="num">Transaksi</th>
                            <th class="num">Fee layanan</th>
                            <th class="num">Fee payment gateway</th>
                            <th class="num">Biaya Duitku</th>
                            <th class="num">Laba</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td>
                                    {{ $customer->company ?: ($customer->name ?: '-') }}<span class="sub">{{ $customer->email }}</span>
                                    <span class="sub">{{ $customer->apps->map(fn ($a) => MutationLedger::appLabel($a->app).' '.$rp($a->profit))->implode(' · ') }}</span>
                                </td>
                                <td class="num">{{ number_format((int) $customer->trx, 0, ',', '.') }}</td>
                                <td class="num">{{ $rp($customer->service) }}</td>
                                <td class="num">{{ $rp($customer->gateway) }}</td>
                                <td class="num text-danger">{{ $customer->duitku ? $rp(-$customer->duitku) : '' }}</td>
                                <td class="num fw-semibold {{ $customer->profit < 0 ? 'text-danger' : '' }}">{{ $rp($customer->profit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada transaksi di periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="small text-muted mt-3">
                Laba = fee layanan + fee payment gateway − biaya Duitku (+ sewa paket, − bonus referral). Sewa dihitung saat paket dibeli.
                Biaya Duitku QRIS dihitung dari tarif default di Payment Gateway; biaya transfer dari pengaturan Disbursement.
                Koreksi saldo admin bukan laba: masuk {{ $rp($adjustment['in']) }}, keluar {{ $rp($adjustment['out']) }} di periode ini.
            </div>
        </div>
    </div>
@endsection
