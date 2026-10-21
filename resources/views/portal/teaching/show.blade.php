@extends('layouts.portal')

@section('title', 'Absen Murid')

@use('App\Models\ScheduleAttendance')

@php
    $options = [
        ScheduleAttendance::STATUS_PRESENT => ['Hadir', 'success', 'ri-check-line'],
        ScheduleAttendance::STATUS_EXCUSED => ['Izin', 'warning', 'ri-hand-heart-line'],
        ScheduleAttendance::STATUS_ABSENT => ['Tidak Hadir', 'danger', 'ri-close-line'],
    ];
@endphp

@section('content')
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('portal.absen') }}" class="d-inline-block text-muted mb-3"><i class="ri-arrow-left-line"></i> Kembali</a>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card portal-card mb-0">
                <div class="card-body">
                    <span class="badge {{ $session->statusBadge() }} mb-2">{{ $session->statusLabel() }}</span>
                    <h5 class="mb-1">{{ $session->grade?->label() }}</h5>
                    <div class="text-muted mb-3">{{ $session->start_at->translatedFormat('l, d F Y') }} · {{ $session->timeRange() }}</div>
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-muted">Tempat</dt><dd class="col-7">{{ $session->branch?->name }}</dd>
                        <dt class="col-5 fw-normal text-muted">Ruang</dt><dd class="col-7">{{ $session->room?->name }}</dd>
                        <dt class="col-5 fw-normal text-muted">Jumlah murid</dt><dd class="col-7">{{ $session->attendances->count() }}</dd>
                        @if ($session->notes) <dt class="col-5 fw-normal text-muted">Catatan</dt><dd class="col-7">{{ $session->notes }}</dd> @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card portal-card mb-0">
                <div class="card-body">
                    @if ($session->status === 'scheduled' && $session->start_at->isFuture())
                        <div class="alert alert-info py-2">Absen bisa diisi mulai {{ $session->start_at->translatedFormat('d M, H:i') }}.</div>
                    @elseif (! $canFill && $session->status === 'scheduled')
                        <div class="alert alert-secondary py-2">Absen sesi ini sedang tidak bisa diisi dari portal. Hubungi admin branch.</div>
                    @endif

                    <form method="POST" action="{{ route('portal.absen.save', $session) }}">
                        @csrf
                        @method('PUT')
                        @forelse ($session->attendances as $attendance)
                            <div class="py-3 {{ $loop->last ? '' : 'border-bottom' }}">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <div class="fw-semibold flex-grow-1">{{ $attendance->student?->name }}</div>
                                    @unless ($canFill)
                                        <span class="badge {{ $attendance->statusBadge() }}">{{ $attendance->status === ScheduleAttendance::STATUS_ABSENT ? 'Tidak Hadir' : $attendance->statusLabel() }}</span>
                                    @endunless
                                </div>
                                @if ($canFill)
                                    <div class="row g-2 mb-2">
                                        @foreach ($options as $status => [$label, $color, $icon])
                                            <div class="col-4">
                                                <input type="radio" class="btn-check" name="statuses[{{ $attendance->student_id }}]" value="{{ $status }}" id="st-{{ $attendance->id }}-{{ $status }}"
                                                    @checked(old('statuses.'.$attendance->student_id, $attendance->status) === $status) required>
                                                <label class="btn btn-outline-{{ $color }} w-100" for="st-{{ $attendance->id }}-{{ $status }}"><i class="{{ $icon }}"></i> {{ $label }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="text" name="notes[{{ $attendance->student_id }}]" maxlength="255" class="form-control form-control-sm"
                                        value="{{ old('notes.'.$attendance->student_id, $attendance->notes) }}" placeholder="Catatan untuk murid/orang tua (opsional), mis. materi hari ini">
                                @elseif ($attendance->notes)
                                    <div class="small text-muted">{{ $attendance->notes }}</div>
                                @endif
                            </div>
                        @empty
                            @include('portal._empty', ['icon' => 'ri-user-unfollow-line', 'title' => 'Sesi ini belum punya murid'])
                        @endforelse

                        @if ($canFill && $session->attendances->isNotEmpty())
                            <button type="submit" class="btn btn-primary w-100 mt-3">Simpan Absen</button>
                            <div class="small text-muted text-center mt-2">Setelah disimpan, sesi ditandai selesai dan catatan terlihat oleh murid/orang tua.</div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
