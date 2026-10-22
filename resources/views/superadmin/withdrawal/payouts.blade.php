@extends('layouts.dashboard')

@section('title', 'Disbursement Owner')
@section('page-section', 'Superadmin')

@php($rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    @include('superadmin.withdrawal._tabs', ['active' => 'payouts'])

    <div class="card">
        <div class="card-body">
            @include('shared.filters', [
                'placeholder' => 'Referensi / penerima / owner',
                'selectLabel' => 'Status',
                'selectName' => 'status',
                'selectOptions' => \App\Models\Disbursement::STATUS_LABELS,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>Owner · Branch</th>
                            <th>Penerima</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Dikirim</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($disbursements as $disbursement)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $disbursement->reference }}</span>
                                    <div class="small text-muted">{{ $disbursement->source === 'api' ? 'API' : 'Dashboard' }}</div>
                                </td>
                                <td>
                                    {{ $disbursement->user?->name ?? '-' }}
                                    <div class="text-muted small">{{ $disbursement->branch?->name }}</div>
                                </td>
                                <td>
                                    {{ $disbursement->bank_name }} ••••{{ $disbursement->account_last4 }}
                                    <div class="text-muted small">a.n. {{ $disbursement->account_name }}</div>
                                </td>
                                <td class="text-end">{{ $rupiah($disbursement->amount) }}</td>
                                <td class="text-end fw-semibold">{{ $rupiah($disbursement->net_amount) }}</td>
                                <td>
                                    <span class="badge {{ $disbursement->statusBadge() }}">{{ $disbursement->statusLabel() }}</span>
                                    @if ($disbursement->failure_reason)
                                        <div class="text-muted small text-wrap" style="max-width: 280px">{{ $disbursement->failure_reason }}</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $disbursement->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($disbursement->needsResolve())
                                        <div class="d-flex gap-1 justify-content-end">
                                            <form method="POST" action="{{ route('superadmin.payouts.check', $disbursement) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Cek Status</button>
                                            </form>
                                            <button class="btn btn-sm btn-warning" type="button" data-bs-toggle="collapse" data-bs-target="#act-{{ $disbursement->id }}">Selesaikan</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if ($disbursement->needsResolve())
                                <tr class="collapse" id="act-{{ $disbursement->id }}">
                                    <td colspan="8" class="bg-light">
                                        <p class="small text-muted mb-2">Pastikan dulu di dashboard Duitku › Laporan Disbursement apakah dana benar-benar terkirim.</p>
                                        <form method="POST" action="{{ route('superadmin.payouts.resolve', $disbursement) }}" class="d-flex flex-wrap gap-2"
                                            onsubmit="return confirm('Yakin dengan hasil pengecekan ini?')">
                                            @csrf
                                            <input type="text" name="note" class="form-control flex-grow-1 w-auto" maxlength="300" required placeholder="Catatan hasil cek (mis. sudah ada di laporan Duitku)">
                                            <button name="sent" value="1" class="btn btn-success text-nowrap">Dana Terkirim</button>
                                            <button name="sent" value="0" class="btn btn-outline-danger text-nowrap">Tidak Terkirim, Kembalikan Saldo</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada disbursement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $disbursements->links() }}</div>
        </div>
    </div>
@endsection
