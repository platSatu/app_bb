@extends('layouts.dashboard')

@section('title', 'Penarikan Saldo')
@section('page-section', 'Superadmin')

@php($rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    @include('superadmin.withdrawal._tabs', ['active' => 'requests'])

    <div class="card">
        <div class="card-body">
            @include('shared.filters', [
                'placeholder' => 'Nama / email / referensi',
                'selectLabel' => 'Status',
                'selectName' => 'status',
                'selectOptions' => \App\Models\WalletWithdrawal::STATUS_LABELS,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>User</th>
                            <th>Rekening</th>
                            <th class="text-end">Ditarik</th>
                            <th class="text-end">Biaya</th>
                            <th class="text-end">Dikirim</th>
                            <th>Status</th>
                            <th>Diajukan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($withdrawals as $withdrawal)
                            <tr>
                                <td class="fw-semibold">{{ $withdrawal->reference }}</td>
                                <td>
                                    {{ $withdrawal->user?->name ?? '-' }}
                                    <div class="text-muted small">{{ $withdrawal->user?->email }}</div>
                                </td>
                                <td>
                                    {{ $withdrawal->bank_name }} ••••{{ $withdrawal->account_last4 }}
                                    <div class="text-muted small">a.n. {{ $withdrawal->account_name }}</div>
                                </td>
                                <td class="text-end">{{ $rupiah($withdrawal->amount) }}</td>
                                <td class="text-end text-muted">{{ $rupiah($withdrawal->fee) }}</td>
                                <td class="text-end fw-semibold">{{ $rupiah($withdrawal->net_amount) }}</td>
                                <td>
                                    <span class="badge {{ $withdrawal->statusBadge() }}">{{ $withdrawal->statusLabel() }}</span>
                                    @if ($withdrawal->rejection_reason || $withdrawal->failure_reason)
                                        <div class="text-muted small text-wrap" style="max-width: 280px">{{ $withdrawal->rejection_reason ?: $withdrawal->failure_reason }}</div>
                                    @endif
                                    @if ($withdrawal->approver)
                                        <div class="text-muted small">oleh {{ $withdrawal->approver->name }}</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $withdrawal->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($withdrawal->status === \App\Models\WalletWithdrawal::STATUS_PENDING)
                                        <div class="d-flex gap-1 justify-content-end">
                                            <form method="POST" action="{{ route('superadmin.withdrawals.approve', $withdrawal) }}"
                                                onsubmit="return confirm({{ Js::from('Kirim '.$rupiah($withdrawal->net_amount).' ke '.$withdrawal->bank_name.' a.n. '.$withdrawal->account_name.' sekarang? Dana langsung ditransfer lewat Duitku.') }})">
                                                @csrf
                                                <button class="btn btn-sm btn-success">Setujui & Kirim</button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#act-{{ $withdrawal->id }}">Tolak</button>
                                        </div>
                                    @elseif ($withdrawal->needsResolve())
                                        <div class="d-flex gap-1 justify-content-end">
                                            <form method="POST" action="{{ route('superadmin.withdrawals.check', $withdrawal) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Cek Status</button>
                                            </form>
                                            <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#act-{{ $withdrawal->id }}">Selesaikan</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if ($withdrawal->status === \App\Models\WalletWithdrawal::STATUS_PENDING || $withdrawal->needsResolve())
                                <tr class="collapse" id="act-{{ $withdrawal->id }}">
                                    <td colspan="9" class="bg-light">
                                        @if ($withdrawal->status === \App\Models\WalletWithdrawal::STATUS_PENDING)
                                            <form method="POST" action="{{ route('superadmin.withdrawals.reject', $withdrawal) }}" class="d-flex gap-2">
                                                @csrf
                                                <input type="text" name="reason" class="form-control" maxlength="500" required placeholder="Alasan penolakan (dibaca user)">
                                                <button class="btn btn-danger text-nowrap">Tolak & Kembalikan Saldo</button>
                                            </form>
                                        @else
                                            <p class="small text-muted mb-2">Pastikan dulu di dashboard Duitku › Laporan Disbursement apakah dana benar-benar terkirim.</p>
                                            <form method="POST" action="{{ route('superadmin.withdrawals.resolve', $withdrawal) }}" class="d-flex flex-wrap gap-2"
                                                onsubmit="return confirm('Yakin dengan hasil pengecekan ini?')">
                                                @csrf
                                                <input type="text" name="note" class="form-control flex-grow-1 w-auto" maxlength="300" required placeholder="Catatan hasil cek (mis. sudah ada di laporan Duitku)">
                                                <button name="sent" value="1" class="btn btn-success text-nowrap">Dana Terkirim</button>
                                                <button name="sent" value="0" class="btn btn-outline-danger text-nowrap">Tidak Terkirim, Kembalikan Saldo</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada pengajuan tarik saldo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $withdrawals->links() }}</div>
        </div>
    </div>
@endsection
