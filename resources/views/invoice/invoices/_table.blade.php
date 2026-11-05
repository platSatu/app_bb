{{--
    Daftar invoice (owner & superadmin). 5 Nov 2026: tampilan ringkas, maksimal 2 baris per sel
    (info utama + detail kecil abu-abu), teks panjang dipotong dengan isi lengkap di tooltip.
--}}
@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))
@once
    @push('styles')
        <style>
            .inv-table { font-size: .875rem; }
            .inv-table th { font-weight: 600; white-space: nowrap; vertical-align: bottom; }
            .inv-table th small { display: block; font-weight: 400; font-size: .72rem; color: var(--bs-secondary-color, #6c757d); }
            .inv-table td { vertical-align: middle; padding-top: .7rem; padding-bottom: .7rem; }
            .inv-table .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); line-height: 1.35; margin-top: 2px; }
            .inv-table .clip { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .inv-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .inv-table .nil { color: var(--bs-tertiary-color, #adb5bd); }
        </style>
    @endpush
@endonce
<div class="table-responsive">
    <table class="table table-hover mb-0 inv-table">
        {{-- Judul kolom tidak boleh turun baris; kalau layar sempit, tabel digeser ke kanan. --}}
        <thead class="table-light">
            <tr>
                <th>Nomor</th>
                <th>Customer</th>
                <th>Keterangan</th>
                <th class="num">Tagihan</th>
                <th class="num">Fee</th>
                <th class="num">Diterima<small>masuk ke saldo</small></th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                @php($paid = $item->isPaid())
                <tr>
                    <td class="text-nowrap">
                        <a href="{{ route($routes.'.show', $item) }}" class="fw-semibold text-body">{{ $item->number }}</a>
                        <span class="sub">
                            {{ $item->sourceLabel() }}
                            @if ($isAdmin) · {{ \Illuminate\Support\Str::limit(trim(($item->branch?->company?->name ?? '').' '.($item->branch?->name ? '— '.$item->branch->name : '')), 34) }} @endif
                        </span>
                    </td>
                    <td style="max-width: 220px">
                        <span class="clip" title="{{ $item->customer_name }}">{{ $item->customer_name }}</span>
                        @php($contact = $item->customer_email ?: ($item->customer_phone ? '+'.$item->customer_phone : ''))
                        @if ($contact) <span class="sub clip" title="{{ $contact }}">{{ $contact }}</span> @endif
                    </td>
                    <td style="min-width: 200px; max-width: 280px">
                        <span class="clip" title="{{ $item->description }}">{{ $item->description }}</span>
                        @if ($item->external_id) <span class="sub clip" title="Ref: {{ $item->external_id }}">Ref: {{ $item->external_id }}</span> @endif
                    </td>
                    {{-- Fee & jumlah diterima baru ada setelah lunas (paid_amount = fee + net_amount). --}}
                    <td class="num">{{ $rp($paid ? $item->paid_amount : $item->amount) }}</td>
                    <td class="num">@if ($paid)<span class="text-muted">{{ $rp($item->fee) }}</span>@else<span class="nil">—</span>@endif</td>
                    <td class="num">@if ($paid)<span class="fw-semibold" style="color:#16a34a">+{{ $rp($item->net_amount) }}</span>@else<span class="nil">—</span>@endif</td>
                    <td class="text-nowrap {{ $item->isOverdue() ? 'text-danger' : '' }}">
                        @if ($item->due_date)
                            {{ $item->due_date->translatedFormat('d M Y') }}
                        @elseif ($item->expires_at)
                            {{ $item->expires_at->translatedFormat('d M Y') }}
                            <span class="sub">s.d. pukul {{ $item->expires_at->format('H:i') }}</span>
                        @else
                            <span class="nil">—</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span>
                        @if ($paid && $item->paid_at) <span class="sub">{{ $item->paid_at->translatedFormat('d M, H:i') }}</span> @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if (! $isAdmin && $item->isUnpaid())
                            <button type="button" class="btn btn-sm btn-outline-primary" data-copy="{{ $item->payUrl() }}" title="Salin link bayar" aria-label="Salin link bayar"><i class="ri-link"></i></button>
                        @endif
                        <a href="{{ route($routes.'.show', $item) }}" class="btn btn-sm btn-outline-secondary" title="Detail" aria-label="Detail"><i class="ri-eye-line"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada invoice.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('invoice.invoices._table-script')
