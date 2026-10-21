@extends('layouts.dashboard')

@section('title', 'Dashboard')

@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp

@section('content')
    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $greeting }}, {{ Auth::user()->name }}</h5>
                <p class="text-muted mb-0">{{ now()->translatedFormat('l, d F Y') }} · Ini ringkasan yang perlu Anda lihat hari ini.</p>
            </div>
        </div>
    </div>

    @if ($owner && ! $hasBranch)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="ri-store-2-line fs-5"></i>
            <div class="flex-grow-1">Mulai dengan membuat branch pertama Anda. Semua aplikasi (jadwal, invoice, WhatsApp) tercatat per branch.</div>
            <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
        </div>
    @elseif ($owner)
        <div class="row g-3">
            <div class="{{ $usesSchedule ? 'col-xl-5' : 'col-12' }}">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="ri-notification-badge-line text-primary me-1"></i> Perlu Tindakan</h6>
                        @forelse ($actions as $item)
                            <a href="{{ $item['url'] }}" class="d-flex align-items-center gap-3 p-2 rounded-3 text-body text-decoration-none border mb-2">
                                <span class="rounded-circle bg-{{ $item['color'] }}-subtle text-{{ $item['color'] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px">
                                    <i class="{{ $item['icon'] }} fs-5"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <div><strong>{{ $item['count'] }}</strong> {{ $item['label'] }}</div>
                                    @if ($item['note']) <div class="small text-muted">{{ $item['note'] }}</div> @endif
                                </div>
                                <i class="ri-arrow-right-s-line fs-5 text-muted"></i>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="ri-checkbox-circle-line fs-1 text-success d-block mb-2"></i>
                                <div class="fw-semibold text-body">Semua beres</div>
                                <div class="small">Tidak ada yang perlu ditindaklanjuti saat ini.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            @if ($usesSchedule)
                <div class="col-xl-7">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="ri-calendar-2-line text-primary me-1"></i> Jadwal Hari Ini</h6>
                                <a href="{{ route('schedules.sessions.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]) }}" class="small">Lihat semua</a>
                            </div>
                            @if ($today->isEmpty())
                                <div class="text-center text-muted py-4">
                                    <i class="ri-cup-line fs-1 d-block mb-2 opacity-50"></i>
                                    <div class="fw-semibold text-body">Tidak ada sesi hari ini</div>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Jam</th>
                                                <th>Pelajaran</th>
                                                <th>Pengajar</th>
                                                <th>Murid</th>
                                                <th>Ruang</th>
                                                @if ($multiBranch) <th>Branch</th> @endif
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($today as $session)
                                                <tr @class(['table-primary' => $session->status === 'scheduled' && $session->start_at->isPast() && $session->end_at->isFuture()])>
                                                    <td class="text-nowrap">
                                                        <a href="{{ route('schedules.sessions.show', $session) }}" class="fw-semibold">{{ $session->timeRange() }}</a>
                                                    </td>
                                                    <td>{{ $session->grade?->label() }}</td>
                                                    <td>{{ $session->teacher?->name }}</td>
                                                    <td class="small">{{ $session->attendances->pluck('student.name')->filter()->join(', ') ?: '-' }}</td>
                                                    <td>{{ $session->room?->name }}</td>
                                                    @if ($multiBranch) <td class="small">{{ $session->branch?->name }}</td> @endif
                                                    <td>
                                                        @if ($session->status === 'scheduled' && $session->start_at->isPast() && $session->end_at->isFuture())
                                                            <span class="badge bg-primary">Sedang berlangsung</span>
                                                        @elseif ($session->status === 'scheduled' && $session->end_at->isPast())
                                                            <span class="badge bg-danger-subtle text-danger">Belum diabsen</span>
                                                        @else
                                                            <span class="badge {{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
