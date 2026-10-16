@extends('layouts.dashboard')

@section('title', 'Detail Sesi')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php
    $rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    $scheduled = $session->status === \App\Models\ScheduleSession::STATUS_SCHEDULED;
    $started = $session->start_at->isPast();
    $canFill = $canManage && $session->status !== \App\Models\ScheduleSession::STATUS_CANCELLED && $started;
@endphp

@section('content')
    @include('schedule._tabs')

    <div class="mb-3">
        <a href="{{ route($routes.'.sessions.index', ['date_from' => $session->date->copy()->startOfWeek()->toDateString(), 'date_to' => $session->date->copy()->endOfWeek()->toDateString()]) }}" class="text-muted">
            <i class="ri-arrow-left-line"></i> Kembali ke daftar sesi
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <span class="badge {{ $session->statusBadge() }} mb-2">{{ $session->statusLabel() }}</span>
                    <h5 class="mb-1">{{ $session->grade?->label() }}</h5>
                    <div class="text-muted mb-3">{{ $session->start_at->translatedFormat('l, d F Y') }} · {{ $session->timeRange() }}</div>
                    <dl class="row small mb-0">
                        <dt class="col-5">Pengajar</dt><dd class="col-7">{{ $session->teacher?->name }}</dd>
                        <dt class="col-5">Ruangan</dt><dd class="col-7">{{ $session->room?->name }}</dd>
                        <dt class="col-5">Jenis</dt><dd class="col-7">{{ $session->isMakeup() ? 'Sesi pengganti' : ($session->routine?->isGroup() ? 'Kelas grup' : 'Privat') }}</dd>
                        <dt class="col-5">Per murid</dt><dd class="col-7">{{ $rupiah($session->grade?->pricePerMeeting() ?? 0) }}</dd>
                        @if ($session->moved_at)
                            <dt class="col-5">Dipindah</dt><dd class="col-7">{{ $session->moved_at->translatedFormat('d M Y H:i') }}</dd>
                        @endif
                        @if ($session->cancel_reason)
                            <dt class="col-5">Alasan batal</dt><dd class="col-7">{{ $session->cancel_reason }}</dd>
                        @endif
                        @if ($session->notes)
                            <dt class="col-5">Catatan</dt><dd class="col-7">{{ $session->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <h6 class="mb-3">Absensi</h6>
                    @if (! $started && $scheduled)
                        <div class="alert alert-info py-2">Absensi bisa diisi setelah sesi dimulai.</div>
                    @endif

                    <form method="POST" action="{{ route($routes.'.sessions.attendance', $session) }}">
                        @csrf
                        @method('PUT')
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Murid</th>
                                        <th>Kehadiran</th>
                                        <th>Catatan</th>
                                        @if ($session->status === 'done') <th class="text-end">Honor Pengajar</th> @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($session->attendances as $attendance)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $attendance->student?->name }}</div>
                                                @if ($attendance->replaces)
                                                    <div class="text-muted small">Pengganti sesi {{ $attendance->replaces->session?->start_at->translatedFormat('d M Y') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFill)
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        @foreach ([\App\Models\ScheduleAttendance::STATUS_PRESENT => 'success', \App\Models\ScheduleAttendance::STATUS_ABSENT => 'danger', \App\Models\ScheduleAttendance::STATUS_EXCUSED => 'warning'] as $status => $color)
                                                            <input type="radio" class="btn-check" name="statuses[{{ $attendance->student_id }}]" value="{{ $status }}" id="st-{{ $attendance->id }}-{{ $status }}"
                                                                @checked(old('statuses.'.$attendance->student_id, $attendance->status) === $status) required>
                                                            <label class="btn btn-outline-{{ $color }}" for="st-{{ $attendance->id }}-{{ $status }}">{{ \App\Models\ScheduleAttendance::STATUS_LABELS[$status] }}</label>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="badge {{ $attendance->statusBadge() }}">{{ $attendance->statusLabel() }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFill)
                                                    <input type="text" name="notes[{{ $attendance->student_id }}]" maxlength="255" class="form-control form-control-sm" value="{{ old('notes.'.$attendance->student_id, $attendance->notes) }}">
                                                @else
                                                    <span class="small text-muted">{{ $attendance->notes ?: '-' }}</span>
                                                @endif
                                            </td>
                                            @if ($session->status === 'done')
                                                <td class="text-end small">{{ $rupiah($attendance->teacher_fee) }}</td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">Sesi ini belum punya murid. Tambahkan murid di Jadwal Rutin.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($canFill && $session->attendances->isNotEmpty())
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="small text-muted">Hadir & Alpa dihitung honor. Izin tidak, dan bisa dibuatkan sesi pengganti.</span>
                                <button type="submit" class="btn btn-primary">{{ $session->status === 'done' ? 'Perbarui Absensi' : 'Simpan & Selesaikan Sesi' }}</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
        @php($excused = $session->status === 'done' ? $session->attendances->where('status', \App\Models\ScheduleAttendance::STATUS_EXCUSED) : collect())

        <div class="row g-3 mt-0">
            @if ($scheduled)
                <div class="col-lg-6">
                    <div class="card mb-0 h-100">
                        <div class="card-body">
                            <h6>Pindahkan Sesi Ini</h6>
                            <p class="small text-muted">Hanya sesi ini yang pindah; jadwal rutin tetap. Jatah pertemuan bulannya tidak berubah.</p>
                            @include('schedule.sessions._slot-form', ['action' => route($routes.'.sessions.move', $session), 'method' => 'PUT', 'button' => 'Pindahkan', 'default' => $session])
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card mb-0 h-100">
                        <div class="card-body">
                            <h6>Batalkan Sesi</h6>
                            <p class="small text-muted">Sesi yang dibatalkan tidak dihitung honor dan tetap memakai jatah pertemuan bulan ini. Kalau hanya berubah waktu, lebih baik dipindahkan.</p>
                            <form method="POST" action="{{ route($routes.'.sessions.cancel', $session) }}" onsubmit="return confirm('Batalkan sesi ini?')">
                                @csrf
                                <input type="text" name="reason" maxlength="500" class="form-control mb-2 @error('reason') is-invalid @enderror" placeholder="Alasan, mis. pengajar sakit" required>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <button class="btn btn-outline-danger">Batalkan Sesi</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @foreach ($excused as $attendance)
                <div class="col-lg-6">
                    <div class="card mb-0 h-100">
                        <div class="card-body">
                            <h6>Sesi Pengganti · {{ $attendance->student?->name }}</h6>
                            @if ($attendance->makeupSession)
                                <p class="small mb-2">Sudah dibuat: {{ $attendance->makeupSession->start_at->translatedFormat('l, d M Y H:i') }} ({{ $attendance->makeupSession->statusLabel() }})</p>
                                <a href="{{ route($routes.'.sessions.show', $attendance->makeupSession) }}" class="btn btn-sm btn-outline-secondary">Lihat sesi pengganti</a>
                            @else
                                <p class="small text-muted">Murid izin. Buat sesi pengganti supaya pertemuannya tidak hilang.</p>
                                @include('schedule.sessions._slot-form', ['action' => route($routes.'.sessions.makeup', [$session, $attendance]), 'method' => 'POST', 'button' => 'Buat Sesi Pengganti', 'default' => null])
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
