@extends('layouts.dashboard')

@section('title', 'Tagihan Saya')
@section('page-section', 'Tagihan')

@php
    $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
@endphp

@push('styles')
    <style>
        .bill-group { border: 1px solid #eef0f3; border-radius: 1rem; overflow: hidden; margin-bottom: 1rem; }
        .bill-group-head { background: #f8f9fb; padding: .75rem 1rem; font-weight: 600; }
        .bill-row { display: flex; align-items: center; gap: .9rem; padding: .9rem 1rem; border-top: 1px solid #f1f3f5; cursor: pointer; margin: 0; }
        .bill-row:has(:checked) { background: rgba(var(--pe-primary-rgb), .06); }
        .bill-row:has(:disabled) { cursor: not-allowed; opacity: .6; }
        .bill-row .form-check-input { width: 1.3rem; height: 1.3rem; margin: 0; float: none; flex-shrink: 0; }
        .pay-bar { position: sticky; bottom: 0; z-index: 10; border: 0; border-radius: 1rem; box-shadow: 0 -8px 30px -12px rgba(16,24,40,.28); }
        @media (max-width: 575.98px) { .bill-row { flex-wrap: wrap; } .bill-row > .text-end { width: 100%; text-align: left !important; padding-left: 2.2rem; } }
    </style>
@endpush

@section('content')
    @if ($tabs === null)
        <div class="card"><div class="card-body text-center p-4 p-md-5">
            <i class="ri-mail-check-line fs-1 text-primary"></i>
            <h5 class="fw-semibold mt-2">Verifikasi email dulu, yuk</h5>
            <p class="text-muted">Demi keamanan, tagihan baru bisa dilihat setelah email <strong>{{ $user->email }}</strong> terverifikasi.</p>
            @if (session('status') === 'verification-link-sent') <div class="alert alert-success d-inline-block">Link verifikasi sudah dikirim. Cek kotak masuk atau folder spam ya.</div> @endif
            <form method="POST" action="{{ route('verification.send') }}">@csrf <button class="btn btn-primary"><i class="ri-send-plane-line me-1"></i>Kirim Link Verifikasi</button></form>
        </div></div>
    @elseif (! $tabs)
        <div class="card"><div class="card-body">
            @include('portal._empty', ['icon' => 'ri-bill-line', 'title' => 'Belum ada tagihan untuk Anda', 'text' => 'Tagihan muncul di sini kalau penagih memakai email '.$user->email.'.'])
        </div></div>
    @else
        @if (count($tabs) > 1)
            <ul class="nav nav-tabs flex-nowrap text-nowrap mb-3" style="overflow-x: auto;">
                @foreach ($tabs as $id => $label)
                    <li class="nav-item"><a class="nav-link {{ $active === $id ? 'active' : '' }}" href="{{ route('bills.index', ['cabang' => $id]) }}"><i class="ri-store-2-line me-1"></i>{{ $label }}</a></li>
                @endforeach
            </ul>
        @endif

        <div class="d-flex gap-2 mb-3">
            <a href="{{ route('bills.index', ['cabang' => $active]) }}" class="btn btn-sm rounded-pill {{ $history ? 'btn-light' : 'btn-primary' }}"><i class="ri-time-line me-1"></i>Belum Dibayar</a>
            <a href="{{ route('bills.index', ['cabang' => $active, 'lihat' => 'riwayat']) }}" class="btn btn-sm rounded-pill {{ $history ? 'btn-primary' : 'btn-light' }}"><i class="ri-checkbox-circle-line me-1"></i>Riwayat Lunas</a>
        </div>

        @if (session('recheck')) <a href="{{ session('recheck') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="ri-refresh-line me-1"></i>Cek lagi</a> @endif

        @if ($history)
            <div class="card"><div class="card-body">
                @forelse ($history as $invoice)
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                        <div><div class="fw-semibold">{{ $invoice->description }}</div><div class="small text-muted">{{ $invoice->number }} · dibayar {{ $invoice->paid_at?->translatedFormat('d M Y H:i') }}</div></div>
                        <div class="text-end text-nowrap">{{ $rp($invoice->paid_amount) }}<div><a href="{{ $invoice->payUrl() }}" target="_blank" rel="noopener" class="small">Lihat bukti</a></div></div>
                    </div>
                @empty
                    @include('portal._empty', ['icon' => 'ri-checkbox-circle-line', 'title' => 'Belum ada tagihan yang lunas di sini'])
                @endforelse
                <div class="mt-3">{{ $history->links() }}</div>
            </div></div>
        @elseif ($groups->isEmpty())
            <div class="card"><div class="card-body">
                @include('portal._empty', ['icon' => 'ri-emotion-happy-line', 'title' => 'Semua tagihan sudah lunas', 'text' => 'Terima kasih sudah membayar tepat waktu!'])
            </div></div>
        @else
            @foreach ($open as $checkout)
                <div class="alert alert-warning d-flex align-items-center gap-3 flex-wrap">
                    <div class="flex-grow-1"><i class="ri-time-line me-1"></i>Ada pembayaran {{ $rp($checkout->amount) }} yang belum diselesaikan, berlaku sampai pukul {{ $checkout->expires_at->format('H:i') }}.</div>
                    <a href="{{ $checkout->payment_url }}" class="btn btn-warning btn-sm">Lanjutkan Bayar</a>
                </div>
            @endforeach
            @unless ($online) <div class="alert alert-secondary">Pembayaran online untuk penagih ini sedang tidak tersedia. Silakan hubungi penagih langsung ya.</div> @endunless

            <form method="POST" action="{{ route('bills.pay') }}" id="bill-form">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $active }}">
                <div class="card"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h6 class="fw-semibold mb-0">Pilih tagihan yang mau dibayar</h6>
                            <div class="small text-muted">Dalam satu jenis tagihan (mis. SPP), pembayaran dimulai dari bulan yang paling lama.</div>
                        </div>
                        <label class="btn btn-outline-primary btn-sm mb-0"><input type="checkbox" class="form-check-input me-1" id="check-all" style="float: none; margin: 0;"> Pilih semua</label>
                    </div>

                    @foreach ($groups as $name => $items)
                        <div class="bill-group">
                            <div class="bill-group-head"><i class="ri-folder-3-line me-1 text-primary"></i>{{ $name }} <span class="text-muted fw-normal small">· {{ $items->count() }} tagihan</span></div>
                            @foreach ($items as $invoice)
                                <label class="bill-row">
                                    <input type="checkbox" class="form-check-input" name="invoices[]" value="{{ $invoice->id }}" data-total="{{ $invoice->totalDue() }}"
                                        data-group="{{ $invoice->category_id }}" @disabled(! $online || isset($busy[$invoice->id])) @checked(in_array($invoice->id, old('invoices', []), true))>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold">{{ $invoice->description }}</div>
                                        <div class="small text-muted d-flex flex-wrap gap-2">
                                            <span>{{ $invoice->number }}</span>
                                            @if ($invoice->due_date)
                                                <span class="badge {{ $invoice->isOverdue() ? 'bg-danger-subtle text-danger' : 'bg-light text-dark border' }}">{{ $invoice->isOverdue() ? 'Lewat jatuh tempo' : 'Jatuh tempo' }} {{ $invoice->due_date->translatedFormat('d M Y') }}</span>
                                            @endif
                                            @isset($busy[$invoice->id]) <span class="badge bg-warning-subtle text-warning">Sedang menunggu pembayaran</span> @endisset
                                        </div>
                                    </div>
                                    <div class="text-end text-nowrap">
                                        <b>{{ $rp($invoice->totalDue()) }}</b>
                                        @if ($invoice->currentLateFee()) <div class="small text-danger">termasuk denda {{ $rp($invoice->currentLateFee()) }}</div> @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div></div>

                <div class="card pay-bar"><div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="flex-grow-1"><div class="small text-muted" id="pay-count">Belum ada tagihan yang dipilih</div><div class="fs-4 fw-bold" id="pay-total">Rp 0</div></div>
                    <button type="submit" class="btn btn-primary btn-lg px-4" id="pay-button" disabled><i class="ri-secure-payment-line me-1"></i>Bayar Sekarang</button>
                </div></div>
            </form>

            <script>
                (function () {
                    var boxes = Array.from(document.querySelectorAll('[name="invoices[]"]')), all = document.getElementById('check-all');
                    function refresh() {
                        var picked = boxes.filter(b => b.checked), total = picked.reduce((s, b) => s + +b.dataset.total, 0);
                        document.getElementById('pay-count').textContent = picked.length ? picked.length + ' tagihan dipilih' : 'Belum ada tagihan yang dipilih';
                        document.getElementById('pay-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
                        document.getElementById('pay-button').disabled = !picked.length;
                        all.checked = boxes.some(b => !b.disabled) && boxes.every(b => b.disabled || b.checked);
                    }
                    // Urutan per kategori: centang Maret = Januari & Februari ikut; lepas Januari = bulan setelahnya ikut lepas.
                    boxes.forEach(function (box, i) {
                        box.addEventListener('change', function () {
                            if (box.dataset.group) boxes.forEach((o, j) => { if (o.dataset.group === box.dataset.group && !o.disabled && (box.checked ? j < i : j > i)) o.checked = box.checked; });
                            refresh();
                        });
                    });
                    all.addEventListener('change', () => { boxes.forEach(b => { if (!b.disabled) b.checked = all.checked; }); refresh(); });
                    document.getElementById('bill-form').addEventListener('submit', function () {
                        var b = document.getElementById('pay-button'); b.disabled = true; b.textContent = 'Membuka halaman pembayaran...';
                    });
                    refresh();
                })();
            </script>
        @endif
    @endif
@endsection
