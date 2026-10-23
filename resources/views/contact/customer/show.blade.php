@extends('layouts.dashboard')

@section('title', 'Detail Customer')
@section('page-section', $base === 'superadmin.contacts' ? 'Superadmin' : $customer->appLabel())

@php($rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ $customer->displayName() }}</h5>
                    <a href="{{ route($base.'.index') }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i></a>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt class="text-muted fw-normal small">Aplikasi</dt>
                        <dd><span class="badge bg-light text-dark border">{{ $customer->appLabel() }}</span></dd>
                        <dt class="text-muted fw-normal small">WhatsApp</dt>
                        <dd>{{ $customer->phone ? '+'.$customer->phone : '-' }}</dd>
                        <dt class="text-muted fw-normal small">Email</dt>
                        <dd>{{ $customer->email ?: '-' }}</dd>
                        <dt class="text-muted fw-normal small">Branch</dt>
                        <dd>{{ $customer->company?->name }} — {{ $customer->branch?->name }}</dd>
                        <dt class="text-muted fw-normal small">Pertama masuk</dt>
                        <dd>{{ $customer->first_seen_at->translatedFormat('d M Y H:i') }} <span class="text-muted small">({{ $customer->source ?: '-' }})</span></dd>
                        <dt class="text-muted fw-normal small">Terakhir</dt>
                        <dd class="mb-0">{{ $customer->last_seen_at->translatedFormat('d M Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if ($submissions)
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Riwayat di Pages</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Referensi</th><th>Page</th><th>Waktu</th><th class="text-end">Aksi</th></tr></thead>
                                <tbody>
                                    @forelse ($submissions as $submission)
                                        <tr>
                                            <td class="fw-semibold text-nowrap">{{ $submission->reference }}</td>
                                            <td>{{ $submission->page?->name ?? '-' }}</td>
                                            <td class="text-nowrap">{{ $submission->created_at->translatedFormat('d M Y H:i') }}</td>
                                            <td class="text-end">
                                                @if ($submission->page && $submission->ticketOrder)
                                                    <a href="{{ route($pagesRoute.'.ticket.orders.show', [$submission->page, $submission->ticketOrder]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Lihat</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $submissions->links() }}</div>
                    </div>
                </div>
            @elseif ($invoices)
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Riwayat Invoice</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Nomor</th><th>Keterangan</th><th>Status</th><th class="text-end">Nominal</th></tr></thead>
                                <tbody>
                                    @forelse ($invoices as $invoice)
                                        <tr>
                                            <td class="fw-semibold text-nowrap">
                                                @if ($invoiceRoute) <a href="{{ route($invoiceRoute, $invoice) }}">{{ $invoice->number }}</a> @else {{ $invoice->number }} @endif
                                            </td>
                                            <td>{{ $invoice->description }}</td>
                                            <td>{{ $invoice->statusLabel() }}</td>
                                            <td class="text-end text-nowrap">{{ $rp($invoice->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada invoice.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">{{ $invoices->links() }}</div>
                    </div>
                </div>
            @else
                <div class="card"><div class="card-body text-muted">Riwayat customer {{ $customer->appLabel() }} dilihat dari menu aplikasinya.</div></div>
            @endif
        </div>
    </div>
@endsection
