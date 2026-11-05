{{--
    Mutasi saldo bergaya rekening koran (5 Nov 2026): Kredit = uang masuk, Debit = uang keluar.
    Tiap baris maksimal 2 baris teks: info utama + detail kecil abu-abu. Teks panjang dipotong, isi lengkap di tooltip.
    Dipakai di: Saldo (10 mutasi terakhir), History, Superadmin › Riwayat Saldo.
--}}
@php
    $gateway = \App\Models\PaymentGatewaySetting::duitku();
    $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
@endphp
@once
    @push('styles')
        <style>
            .ledger { font-size: .875rem; }
            .ledger th { font-weight: 600; white-space: nowrap; vertical-align: bottom; }
            .ledger th small { display: block; font-weight: 400; font-size: .72rem; color: var(--bs-secondary-color, #6c757d); }
            .ledger td { vertical-align: middle; padding-top: .7rem; padding-bottom: .7rem; }
            .ledger .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); line-height: 1.35; margin-top: 2px; }
            .ledger .clip { display: block; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ledger .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
            .ledger .nil { color: var(--bs-tertiary-color, #adb5bd); }
            .ledger .credit { color: #16a34a; font-weight: 600; }
            .ledger .debit { color: #dc2626; font-weight: 600; }
            .ledger .pending { color: var(--bs-warning-text-emphasis, #997404); }
        </style>
    @endpush
@endonce
<div class="table-responsive">
    <table class="table table-hover mb-0 ledger">
        <thead class="table-light">
            <tr>
                <th>Waktu</th>
                @if ($showUser) <th>User</th> @endif
                <th>Transaksi</th>
                <th class="num">Kredit<small>uang masuk</small></th>
                <th class="num">Debit<small>uang keluar</small></th>
                <th class="num">Saldo Awal</th>
                <th class="num">Saldo Akhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                @php($credit = $transaction->isCredit())
                <tr>
                    <td class="text-nowrap">
                        {{ $transaction->created_at->translatedFormat('d M Y') }}
                        <span class="sub">{{ $transaction->created_at->format('H:i') }}</span>
                    </td>
                    @if ($showUser)
                        <td class="text-nowrap">
                            {{ $transaction->user?->name ?? '-' }}
                            <span class="sub">{{ $transaction->user?->email }}</span>
                        </td>
                    @endif
                    <td style="min-width: 240px">
                        <span class="fw-semibold">{{ $transaction->typeLabel() }}</span>
                        @if ($transaction->description)
                            <span class="sub clip" title="{{ $transaction->description }}">{{ $transaction->description }}</span>
                        @endif
                        @if ($transaction->payment_method || $transaction->isPendingSettlement())
                            <span class="sub">
                                @if ($transaction->payment_method){{ $gateway->methodName($transaction->payment_method) }}@endif
                                @if ($transaction->payment_method && $transaction->isPendingSettlement()) · @endif
                                @if ($transaction->isPendingSettlement())
                                    <span class="pending">Dalam pencairan, bisa ditarik {{ $transaction->available_at->translatedFormat('d M, H:i') }}</span>
                                @endif
                            </span>
                        @endif
                    </td>
                    <td class="num">
                        @if ($credit)<span class="credit">+{{ $rp($transaction->amount) }}</span>@else<span class="nil">—</span>@endif
                    </td>
                    <td class="num">
                        @if (! $credit)<span class="debit">−{{ $rp($transaction->amount) }}</span>@else<span class="nil">—</span>@endif
                    </td>
                    <td class="num text-muted">{{ $rp($transaction->balance_before) }}</td>
                    <td class="num fw-semibold">{{ $rp($transaction->balance_after) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showUser ? 7 : 6 }}" class="text-center text-muted py-4">Belum ada riwayat saldo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
