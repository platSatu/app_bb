@extends('layouts.dashboard')

@section('title', 'Disbursement')
@section('page-section', 'Invoice')

@php($rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    @include('invoice.disbursements._tabs')

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Yang bisa dikirim saat ini</div>
                <div class="fs-4 fw-semibold">{{ $rupiah($sendable) }}</div>
                <div class="small text-muted">Saldo Anda {{ $rupiah($balance) }}
                    @if ($sendable < $balance)
                        · sebagian masih dalam proses pencairan
                    @endif
                </div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Terkirim bulan ini · {{ $branch->name }}</div>
                <div class="fs-4 fw-semibold">{{ $rupiah($sentThisMonth) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">Biaya per transfer · minimal kirim</div>
                <div class="fs-5 fw-semibold">{{ $rupiah($setting->withdrawal_fee) }} · {{ $rupiah($setting->min_amount) }}</div>
                <div class="small text-muted">Biaya dipotong dari jumlah yang dikirim.</div>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="mb-0">Riwayat Disbursement</h6>
                <div class="d-flex gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['export' => 1, 'branch' => $branch->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-file-excel-2-line"></i> Export Excel</a>
                    @if ($available)
                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#send-form" @disabled($recipients->isEmpty())><i class="ri-send-plane-line"></i> Kirim Dana</button>
                    @endif
                </div>
            </div>

            @if ($available)
                <div class="collapse {{ $errors->any() || session('error') ? 'show' : '' }}" id="send-form">
                    <form method="POST" action="{{ route('invoices.disbursements.send') }}" class="row g-2 align-items-end border rounded p-3 mb-3 bg-light"
                        onsubmit="this.querySelector('[type=submit]').disabled = true;">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <input type="hidden" name="request_token" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Rekening penerima</label>
                            <select name="recipient_id" class="form-select" required>
                                <option value="">Pilih rekening</option>
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient->id }}" @selected(old('recipient_id') === $recipient->id)>{{ $recipient->label ? $recipient->label.' · ' : '' }}{{ $recipient->masked() }} a.n. {{ $recipient->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Jumlah (Rp)</label>
                            <input type="number" name="amount" class="form-control" min="{{ $setting->min_amount }}" step="1" required value="{{ old('amount') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Keterangan</label>
                            <input type="text" name="description" class="form-control" maxlength="255" value="{{ old('description') }}" placeholder="Opsional">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary">Kirim</button>
                        </div>
                    </form>
                </div>
                @if ($recipients->isEmpty())
                    <div class="small text-muted mb-3">Tambahkan rekening dulu di tab <a href="{{ route('invoices.disbursements.recipients', ['branch' => $branch->id]) }}">Rekening Penerima</a>.</div>
                @endif
            @endif

            @include('shared.filters', [
                'placeholder' => 'Referensi / external ID / nama',
                'selectLabel' => 'Status',
                'selectName' => 'status',
                'selectOptions' => \App\Models\Disbursement::STATUS_LABELS,
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>Penerima</th>
                            <th class="text-end">Jumlah</th>
                            <th class="text-end">Biaya</th>
                            <th class="text-end">Diterima</th>
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
                                    <div class="small text-muted">{{ $disbursement->source === 'api' ? 'API' : 'Dashboard' }}{{ $disbursement->external_id ? ' · '.$disbursement->external_id : '' }}</div>
                                </td>
                                <td>
                                    {{ $disbursement->account_name }}
                                    <div class="small text-muted">{{ $disbursement->recipient?->label ? $disbursement->recipient->label.' · ' : '' }}{{ $disbursement->bank_name }} ••••{{ $disbursement->account_last4 }}</div>
                                </td>
                                <td class="text-end">{{ $rupiah($disbursement->amount) }}</td>
                                <td class="text-end text-muted">{{ $rupiah($disbursement->fee) }}</td>
                                <td class="text-end fw-semibold">{{ $rupiah($disbursement->net_amount) }}</td>
                                <td>
                                    <span class="badge {{ $disbursement->statusBadge() }}">{{ $disbursement->statusLabel() }}</span>
                                    @if ($disbursement->status === \App\Models\Disbursement::STATUS_FAILED && $disbursement->failure_reason)
                                        <div class="text-muted small text-wrap" style="max-width: 260px">{{ $disbursement->failure_reason }}</div>
                                    @endif
                                    @if ($disbursement->description)
                                        <div class="text-muted small text-wrap" style="max-width: 260px">{{ $disbursement->description }}</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $disbursement->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($disbursement->disburse_id && $disbursement->status !== \App\Models\Disbursement::STATUS_FAILED)
                                        <form method="POST" action="{{ route('invoices.disbursements.check', $disbursement) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">Cek Status</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
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
