@extends('layouts.dashboard')

@section('page-section', 'Pengajar')

@section('title', 'Scan Absen')

@section('content')
    <div class="pb-4">
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-body">
                    @if ($sessions->isEmpty())
                        @include('portal._empty', [
                            'icon' => 'ri-qr-scan-2-line',
                            'title' => 'Tidak ada sesi lagi hari ini',
                            'text' => 'Halaman ini dipakai saat ada sesi mengajar. Absen Anda bisa dimulai '.\App\Services\Schedule\ScheduleSessionService::CHECK_IN_EARLY_MINUTES.' menit sebelum sesi dimulai.',
                        ])
                    @else
                        @include('schedule._qr-scan', ['scanUrl' => route('portal.scan.store')])
                        <ol class="small text-muted ps-3 mb-0">
                            <li>Scan <strong>QR tempat les</strong> yang ditempel di lokasi untuk absen Anda.</li>
                            <li>Scan <strong>kartu absen murid</strong> satu per satu. Setiap murid cukup sekali per sesi.</li>
                            <li>Setelah sesi dimulai, buka <strong>Selesaikan absen</strong> untuk murid yang izin atau tidak hadir.</li>
                        </ol>
                    @endif
                </div>
            </div>
        </div>

        @if ($sessions->isNotEmpty())
            <div class="col-lg-7">
                <div class="card mb-0">
                    <div class="card-body">
                        <h6 class="mb-3">Sesi hari ini</h6>
                        @foreach ($sessions as $session)
                            <div class="d-flex flex-wrap align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <div class="text-center bg-light rounded-3 py-1" style="width:52px">
                                    <div class="fw-semibold">{{ $session->start_at->format('H:i') }}</div>
                                    <div class="small text-muted">{{ $session->end_at->format('H:i') }}</div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->grade?->label() }}</div>
                                    <div class="small text-muted text-truncate">{{ $session->room?->name }} · {{ $session->branch?->name }}</div>
                                    <div class="d-flex flex-wrap gap-2 small mt-1">
                                        @if ($session->teacher_checked_in_at)
                                            <span class="badge bg-success-subtle text-success"><i class="ri-user-follow-line"></i> Anda hadir {{ $session->teacher_checked_in_at->format('H:i') }}</span>
                                            <span class="badge bg-light text-body"><i class="ri-qr-scan-2-line"></i>
                                                <span data-scan-count="{{ $session->id }}">{{ $session->attendances->whereNotNull('scanned_at')->count() }}</span> dari {{ $session->attendances->count() }} murid</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning"><i class="ri-time-line"></i> Anda belum absen</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('portal.absen.show', $session) }}" class="btn btn-sm btn-outline-primary text-nowrap">Selesaikan absen</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
    </div>
@endsection
