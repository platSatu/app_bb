@extends('layouts.dashboard')

@section('title', 'Registrasi Ulang')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-scanners'])

    <div class="row g-3 mb-1">
        <div class="col-md-4">
            <div class="card mb-0"><div class="card-body py-3">
                <div class="text-muted small">Sudah hadir</div>
                <div class="fs-4 fw-semibold">{{ $stats['checked_in'] }} <span class="fs-6 text-muted">/ {{ $stats['total'] }} tiket</span></div>
                <div class="progress mt-2" style="height: 6px;"><div class="progress-bar bg-success" style="width: {{ $stats['total'] ? round($stats['checked_in'] / $stats['total'] * 100) : 0 }}%"></div></div>
            </div></div>
        </div>
        <div class="col-md-8">
            <div class="card mb-0"><div class="card-body py-3 d-flex flex-wrap gap-4">
                @forelse ($stats['categories'] as $row)
                    <div><div class="text-muted small">{{ $row['name'] }}</div><div class="fw-semibold">{{ $row['checked_in'] }} / {{ $row['total'] }}</div></div>
                @empty
                    <div class="text-muted small">Belum ada tiket terjual.</div>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-7">
            @include('page.ticket._scanner-links', [
                'links' => $scanners,
                'type' => \App\Models\TicketScanner::TYPE_CHECKIN,
                'icon' => 'ri-qr-scan-2-line',
                'title' => 'Link Petugas Registrasi Ulang',
                'description' => 'Buat satu link per pintu atau petugas. Petugas cukup membuka link dan memasukkan PIN, tanpa perlu akun. Bisa scan QR e-ticket maupun QR ID card.',
                'placeholder' => 'mis. Pintu A / Andi',
                'emptyText' => 'Belum ada link petugas.',
            ])
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Baru Hadir</h6></div>
                <div class="card-body p-3 p-md-4">
                    @forelse ($recent as $ticket)
                        <div class="d-flex justify-content-between border-bottom py-2 small">
                            <div><div class="fw-semibold">{{ $ticket->holder_name }}</div><div class="text-muted">{{ $ticket->category_name }} · {{ $ticket->booking_code }}</div></div>
                            <div class="text-end text-muted">{{ $ticket->checked_in_at->format('H:i') }}<div>{{ $ticket->scanner?->name }}</div></div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3">Belum ada yang registrasi ulang.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@endsection
