@extends('layouts.dashboard')

@section('page-section', 'Portal')

@section('title', 'Beranda')

@php
    $roles = Auth::user()->portalRoles();
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp

@section('content')
    <div class="pb-4">
    <div class="card bg-primary text-white mb-4">
        <div class="card-body p-4">
            <h5 class="text-white mb-1">{{ $greeting }}, {{ \Illuminate\Support\Str::before(Auth::user()->name, ' ') ?: Auth::user()->name }}</h5>
            <p class="mb-0 text-white-50">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
    </div>

    @foreach ($requests as $item)
        <div class="alert {{ $item->isPending() ? 'alert-warning' : 'alert-secondary' }} d-flex gap-2 align-items-start">
            <i class="{{ $item->isPending() ? 'ri-time-line' : 'ri-close-circle-line' }} fs-5"></i>
            <div>
                @if ($item->isPending())
                    Pendaftaran <strong>{{ $item->name }}</strong> sebagai {{ strtolower($item->typeLabel()) }} di <strong>{{ $item->branch?->name }}</strong> sedang diperiksa admin.
                @else
                    Pendaftaran <strong>{{ $item->name }}</strong> di {{ $item->branch?->name }} belum bisa disetujui.{{ $item->reject_reason ? ' Alasan: '.$item->reject_reason : '' }}
                @endif
            </div>
        </div>
    @endforeach

    @if (! $roles['student'] && ! $roles['teacher'] && $requests->isEmpty())
        <div class="card">
            <div class="card-body">
                @include('portal._empty', ['icon' => 'ri-link', 'title' => 'Akun Anda belum terhubung ke tempat les mana pun', 'text' => 'Minta link pendaftaran ke admin tempat les, lalu buka link tersebut saat sudah masuk.'])
            </div>
        </div>
    @endif

    <div class="row g-3">
        @if ($roles['teacher'])
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="ri-presentation-line text-primary me-1"></i> Mengajar Hari Ini</h6>
                            <a href="{{ route('portal.teaching') }}" class="small">Lihat minggu ini</a>
                        </div>
                        @if ($toFill)
                            <a href="{{ route('portal.absen') }}" class="alert alert-danger d-flex align-items-center gap-2 py-2 text-decoration-none">
                                <i class="ri-error-warning-line fs-5"></i>
                                <span><strong>{{ $toFill }} sesi</strong> belum diabsen. Isi sekarang</span>
                                <i class="ri-arrow-right-line ms-auto"></i>
                            </a>
                        @endif
                        @forelse ($teaching as $session)
                            <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <div class="text-center bg-light rounded-3 py-1" style="width:52px">
                                    <div class="fw-semibold">{{ $session->start_at->format('H:i') }}</div>
                                    <div class="small text-muted">{{ $session->end_at->format('H:i') }}</div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->grade?->label() }}</div>
                                    <div class="small text-muted text-truncate">{{ $session->attendances->pluck('student.name')->filter()->join(', ') ?: 'Belum ada murid' }} · {{ $session->room?->name }} · {{ $session->branch?->name }}</div>
                                </div>
                                <span class="badge {{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
                            </div>
                        @empty
                            @include('portal._empty', ['icon' => 'ri-cup-line', 'title' => 'Tidak ada jadwal mengajar hari ini'])
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if ($roles['student'])
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="ri-calendar-event-line text-primary me-1"></i> Les Berikutnya</h6>
                            <a href="{{ route('portal.schedule') }}" class="small">Lihat semua</a>
                        </div>
                        @forelse ($lessons as $session)
                            <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <div class="text-center bg-primary-subtle text-primary rounded-3 py-1" style="width:52px">
                                    <div class="small">{{ $session->start_at->translatedFormat('D') }}</div>
                                    <div class="fw-semibold fs-5 lh-1">{{ $session->start_at->format('d') }}</div>
                                    <div class="small">{{ $session->start_at->translatedFormat('M') }}</div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $session->grade?->label() }}</div>
                                    <div class="small text-muted text-truncate">
                                        {{ $session->timeRange() }} · {{ $session->teacher?->name }} · {{ $session->room?->name }}
                                    </div>
                                    @if ($multiChild)
                                        <div class="small"><i class="ri-user-smile-line"></i> {{ $session->attendances->pluck('student.name')->join(', ') }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            @include('portal._empty', ['title' => 'Belum ada jadwal les', 'text' => 'Jadwal akan muncul di sini setelah admin mengaturnya.'])
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
    </div>
@endsection
