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
                            'title' => 'Belum ada sesi yang bisa discan',
                            'text' => $later
                                ? 'Sesi berikutnya '.$later->grade?->name.' pukul '.$later->start_at->format('H:i').'. Scan dibuka '.\App\Services\Schedule\ScheduleSessionService::SCAN_EARLY_MINUTES.' menit sebelum sesi dimulai.'
                                : 'Scan dibuka '.\App\Services\Schedule\ScheduleSessionService::SCAN_EARLY_MINUTES.' menit sebelum sesi Anda dimulai sampai sesi selesai.',
                        ])
                    @else
                        @include('schedule._qr-scan', ['scanUrl' => route('portal.scan.store')])
                        <p class="small text-muted mb-0">Hanya murid di sesi Anda yang bisa discan. Setelah sesi dimulai, buka <strong>Selesaikan absen</strong> untuk mengisi murid yang izin atau tidak hadir.</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($sessions->isNotEmpty())
            <div class="col-lg-7">
                <div class="card mb-0">
                    <div class="card-body">
                        <h6 class="mb-3">Sesi yang sedang berlangsung</h6>
                        @foreach ($sessions as $session)
                            <div class="d-flex flex-wrap align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <div class="text-center bg-light rounded-3 py-1" style="width:52px">
                                    <div class="fw-semibold">{{ $session->start_at->format('H:i') }}</div>
                                    <div class="small text-muted">{{ $session->end_at->format('H:i') }}</div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->grade?->label() }}</div>
                                    <div class="small text-muted text-truncate">{{ $session->room?->name }} · {{ $session->branch?->name }}</div>
                                    <div class="small mt-1"><i class="ri-qr-scan-2-line text-success"></i>
                                        <span data-scan-count="{{ $session->id }}">{{ $session->attendances->whereNotNull('scanned_at')->count() }}</span> dari {{ $session->attendances->count() }} murid sudah scan
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
