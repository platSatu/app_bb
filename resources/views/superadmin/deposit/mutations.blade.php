@extends('layouts.dashboard')

@section('title', 'Sett & Lap')
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
        .mutasi-live .live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #adb5bd; margin-right: 4px; vertical-align: middle; }
        .mutasi-live .live-dot.on { background: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, .18); }
        .mutasi-live .live-dot.off { background: #dc2626; }
    </style>
@endpush

@section('content')
    @include('superadmin.deposit._tabs', ['active' => 'mutations'])

    {{-- Saldo asli Duitku vs saldo tercatat bizbos: diperbarui otomatis tiap 30 detik (tanpa tombol). --}}
    <div class="card mb-3 mutasi-live" data-live-url="{{ route('superadmin.mutations.duitku-balance') }}">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-sm-6 col-xl-3 mutasi-stat">
                    <div class="label"><span class="live-dot" data-live-dot></span> Saldo Duitku <span data-live-mode></span></div>
                    <div class="value" data-live-balance>…</div>
                    <div class="small text-muted" data-live-effective>Mengambil saldo dari Duitku…</div>
                </div>
                <div class="col-sm-6 col-xl-3 mutasi-stat">
                    <div class="label">Tercatat sudah cair</div>
                    <div class="value" data-live-settled>{{ $rp($summary['balance_now'] - $summary['pending']) }}</div>
                    <div class="small text-muted">Total tercatat <span data-live-recorded>{{ $rp($summary['balance_now']) }}</span> · menunggu cair <span data-live-pending>{{ $rp($summary['pending']) }}</span></div>
                </div>
                <div class="col-sm-6 col-xl-3 mutasi-stat">
                    <div class="label">Selisih (Duitku − sudah cair)</div>
                    <div class="value" data-live-diff>…</div>
                    <div class="small text-muted" data-live-diff-note>Seharusnya mendekati 0. Sisa selisih = transaksi lama sebelum fee QRIS dicatat, perkiraan tanggal cair, atau top up/tarik manual di Duitku.</div>
                </div>
                <div class="col-sm-6 col-xl-3 mutasi-stat">
                    <div class="label">Total saldo semua user</div>
                    <div class="value">{{ $rp($summary['user_balance']) }}</div>
                    <div class="small text-muted">Uang milik user yang wajib tersedia</div>
                </div>
            </div>
            <div class="small text-muted mt-2" data-live-time></div>
        </div>
    </div>

    {{-- Ringkasan untuk filter yang sama dengan tabel. --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 mb-0 mutasi-stat"><div class="card-body">
                <div class="label">Kredit · uang masuk</div>
                <div class="value text-success">{{ $rp($summary['credit']) }}</div>
                <div class="small text-muted">{{ $summary['credit_count'] }} pembayaran · {{ $period }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 mb-0 mutasi-stat"><div class="card-body">
                <div class="label">Debit · uang keluar</div>
                <div class="value text-danger">{{ $rp($summary['debit']) }}</div>
                <div class="small text-muted">{{ $summary['debit_count'] }} transfer, termasuk biaya Duitku · {{ $period }}</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 mb-0 mutasi-stat"><div class="card-body">
                <div class="label">Saldo tercatat</div>
                <div class="value">{{ $rp($summary['balance']) }}</div>
                <div class="small text-muted">{{ $until ? 'Per '.$until : 'Saat ini' }} · kredit − debit semua sumber</div>
            </div></div>
        </div>
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
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="No. referensi / nama / customer" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Sumber</label>
                    <select name="source" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Services\Deposit\MutationLedger::SOURCES as $key => [$label])
                            <option value="{{ $key }}" @selected($filters['source'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Arah</label>
                    <select name="direction" class="form-select">
                        <option value="">Kredit & Debit</option>
                        <option value="credit" @selected($filters['direction'] === 'credit')>Kredit (masuk)</option>
                        <option value="debit" @selected($filters['direction'] === 'debit')>Debit (keluar)</option>
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
                                        @if ($row->duitku_fee > 0)
                                            <span class="sub">dibayar {{ $rp($row->amount + $row->duitku_fee) }} · fee Duitku {{ $rp($row->duitku_fee) }} ditanggung bizbos</span>
                                        @elseif ($row->duitku_fee !== null)
                                            <span class="sub">utuh · fee Duitku dibayar pembeli</span>
                                        @endif
                                    @else
                                        <span class="nil">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($row->direction === 'debit')
                                        <span class="debit">−{{ $rp($row->amount) }}</span>
                                        <span class="sub">transfer {{ $rp($row->transfer) }} · Duitku {{ $rp($row->duitku_fee) }}</span>
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

            <div class="mt-3">{{ $rows->links() }}</div>

            <div class="small text-muted mt-2">
                @if ($narrowed)
                    Saldo per baris disembunyikan saat memakai filter sumber/arah/cari.
                @endif
                {{ $anchored ? 'Saldo Akhir baris paling baru = saldo Duitku saat ini; ke bawah: Saldo Awal = Saldo Akhir − Kredit + Debit, menjadi Saldo Akhir baris di bawahnya.' : 'Saldo Duitku belum bisa dicek, saldo per baris memakai saldo tercatat bizbos.' }}
                Kredit = yang masuk ke Duitku (sudah dikurangi fee asli Duitku per transaksi; transaksi sebelum 6 Nov 2026 tanpa catatan fee). Debit = nominal transfer + biaya Duitku per transfer.
            </div>
        </div>
    </div>

    <script>
        (function () {
            var box = document.querySelector('[data-live-url]');
            if (!box) return;
            var url = box.getAttribute('data-live-url'), timer = null;
            var $ = function (sel) { return box.querySelector(sel); };
            var rp = function (v) { return (v < 0 ? '−' : '') + 'Rp ' + Math.abs(v).toLocaleString('id-ID'); };

            function render(data) {
                $('[data-live-dot]').className = 'live-dot ' + (data.ok ? 'on' : 'off');
                if (!data.ok) {
                    $('[data-live-effective]').textContent = data.message || 'Saldo Duitku belum bisa dicek.';
                    return;
                }
                $('[data-live-mode]').textContent = data.sandbox ? '(sandbox)' : '';
                $('[data-live-balance]').textContent = rp(data.balance);
                $('[data-live-effective]').textContent = 'Efektif (bisa dipakai transfer): ' + rp(data.effective);
                $('[data-live-recorded]').textContent = rp(data.recorded);
                $('[data-live-pending]').textContent = rp(data.pending);
                $('[data-live-settled]').textContent = rp(data.settled);
                var diff = $('[data-live-diff]');
                diff.textContent = rp(data.difference);
                diff.className = 'value ' + (data.difference < 0 ? 'text-danger' : '');
                // Saldo Duitku lebih kecil dari yang seharusnya: tanda ada yang perlu dicek.
                if (data.difference < 0) {
                    $('[data-live-diff-note]').textContent = 'Saldo Duitku LEBIH KECIL dari catatan yang sudah cair. Cek transfer "Sedang Dicek" atau refund.';
                }
                $('[data-live-time]').textContent = 'Diperbarui otomatis tiap 30 detik · terakhir ' + new Date(data.checked_at).toLocaleTimeString('id-ID');
            }

            function load() {
                if (document.hidden) return;
                fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
                    .then(render)
                    .catch(function () { render({ ok: false, message: 'Koneksi terputus, mencoba lagi…' }); });
            }

            load();
            timer = setInterval(load, 30000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });
        })();
    </script>
@endsection
