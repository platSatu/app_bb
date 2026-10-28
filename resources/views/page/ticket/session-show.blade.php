@extends('layouts.dashboard')

@section('title', 'Daftar Hadir · '.$session->name)
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-sessions'])

    @php
        $percent = $stats['eligible'] ? round($stats['attended'] / $stats['eligible'] * 100) : 0;
    @endphp

    <div class="card">
        <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div>
                <a href="{{ route($routes.'.ticket.sessions.index', $page) }}" class="small text-muted"><i class="ri-arrow-left-line"></i> Semua sesi</a>
                <h5 class="fw-semibold mb-1 mt-1">{{ $session->name }}</h5>
                <div class="text-muted small">{{ $session->timeRange() }}{{ $session->room ? ' · '.$session->room : '' }}</div>
                <span class="badge {{ $session->stateBadge() }} mt-2">{{ $session->stateLabel() }}</span>
            </div>
            <div style="min-width: 240px;">
                <div class="d-flex align-items-end gap-2">
                    <span class="fs-2 fw-bold lh-1">{{ $stats['attended'] }}</span>
                    <span class="text-muted">/ {{ $stats['eligible'] }} peserta hadir</span>
                </div>
                <div class="progress mt-2" style="height: 8px;"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills mb-3 gap-1">
                <li class="nav-item"><a class="nav-link {{ $tab === 'present' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'present', 'page' => null]) }}"><i class="ri-checkbox-circle-line me-1"></i>Sudah hadir ({{ $stats['attended'] }})</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'absent' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['tab' => 'absent', 'page' => null]) }}"><i class="ri-time-line me-1"></i>Belum hadir ({{ max(0, $stats['eligible'] - $stats['attended']) }})</a></li>
            </ul>

            <form method="GET" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="col-12 col-md"><input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama atau kode booking" value="{{ request('search') }}"></div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-outline-success"><i class="ri-file-excel-2-line"></i> Unduh Excel</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        @if ($tab === 'present')
                            <tr><th>Peserta</th><th>Kode Booking</th><th>Waktu Absen</th><th>Petugas</th></tr>
                        @else
                            <tr><th>Peserta</th><th>Kode Booking</th><th>ID Card</th></tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @if ($tab === 'present')
                                <tr>
                                    <td><div class="fw-semibold">{{ $row->ticket?->holder_name }}</div><div class="text-muted small">{{ $row->ticket?->category_name }}</div></td>
                                    <td>{{ $row->ticket?->booking_code }}</td>
                                    <td class="text-nowrap">{{ $row->scanned_at->format('H:i:s') }}<div class="text-muted small">{{ $row->scanned_at->translatedFormat('d M Y') }}</div></td>
                                    <td class="small">{{ $row->scanner?->name ?? '-' }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td><div class="fw-semibold">{{ $row->holder_name }}</div><div class="text-muted small">{{ $row->category_name }}</div></td>
                                    <td>{{ $row->booking_code }}</td>
                                    <td>{!! $row->hasCard() ? '<span class="badge bg-success-subtle text-success">Sudah punya</span>' : '<span class="badge bg-warning-subtle text-warning">Belum cetak</span>' !!}</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                @if (request('search')) Tidak ada peserta yang cocok.
                                @elseif ($tab === 'present') Belum ada yang absen di sesi ini.
                                @else Semua peserta sudah hadir. Mantap!
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
@endsection
