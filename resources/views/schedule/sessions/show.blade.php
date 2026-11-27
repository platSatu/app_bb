@extends('layouts.dashboard')

@section('title', 'Detail Sesi')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php
    $rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
    // Nominal disembunyikan dulu (xxxxxxx); ikon mata menampilkannya. Tidak disimpan, jadi tiap buka halaman tersembunyi lagi.
    $secret = fn ($v) => '<span class="text-nowrap"><span data-secret="'.e($rupiah($v)).'">xxxxxxx</span>'
        .' <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-muted" data-secret-toggle title="Tampilkan nominal" aria-label="Tampilkan nominal"><i class="ri-eye-off-line"></i></button></span>';
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
                        <dt class="col-5">Pengajar</dt>
                        <dd class="col-7">
                            {{ $session->teacher?->name }}
                            @if ($session->teacher_checked_in_at)
                                <div class="text-success"><i class="ri-user-follow-line"></i> Hadir {{ $session->teacher_checked_in_at->format('H:i') }}{{ $session->teacher_checked_in_at->gt($session->start_at) ? ', terlambat '.(int) $session->start_at->diffInMinutes($session->teacher_checked_in_at).' menit' : '' }}</div>
                                @if ($session->teacher_checkin_via === 'whatsapp')
                                    <div class="text-muted"><i class="ri-whatsapp-line"></i> Kelas online, absen lewat WhatsApp</div>
                                @elseif ($session->teacher_checkin_distance !== null)
                                    <div class="text-muted"><i class="ri-map-pin-line"></i> Scan QR ± {{ $session->teacher_checkin_distance }} m dari lokasi</div>
                                @endif
                            @elseif ($session->status !== \App\Models\ScheduleSession::STATUS_CANCELLED && $started)
                                <div class="text-muted">Belum absen scan</div>
                            @endif
                            @if ($session->teacher_unavailable_at && $session->status === \App\Models\ScheduleSession::STATUS_SCHEDULED)
                                <div class="text-danger"><i class="ri-error-warning-line"></i> Mengabarkan berhalangan via WhatsApp {{ $session->teacher_unavailable_at->translatedFormat('d M H:i') }}. Pindahkan jadwal atau ganti pengajar.</div>
                            @endif
                        </dd>
                        <dt class="col-5">Ruangan</dt><dd class="col-7">{{ $session->room?->name }}</dd>
                        <dt class="col-5">Jenis</dt><dd class="col-7">{{ $session->isMakeup() ? 'Sesi pengganti' : ($session->routine?->isGroup() ? 'Kelas grup' : 'Privat') }}</dd>
                        <dt class="col-5">Per murid</dt><dd class="col-7">{!! $secret($session->grade?->pricePerMeeting() ?? 0) !!}</dd>
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
            <div class="card mb-0 h-100" id="absensi" style="scroll-margin-top: 90px">
                <div class="card-body">
                    <h6 class="mb-3">Absensi</h6>
                    @if (! $started && $scheduled)
                        <div class="alert alert-info py-2">Absensi bisa diisi setelah sesi dimulai.</div>
                    @endif

                    <form method="POST" action="{{ route($routes.'.sessions.attendance', $session) }}">
                        @csrf
                        @method('PUT')
                        @if ($canFill && $session->attendances->isNotEmpty())
                            @include('schedule._qr-scan')
                        @endif
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
                                        <tr data-qr-row>
                                            <td class="text-nowrap">
                                                <div class="fw-semibold">{{ $attendance->student?->name }}</div>
                                                @if ($attendance->scanned_at)
                                                    <div class="small text-success"><i class="ri-qr-scan-2-line"></i> Scan pukul {{ $attendance->scanned_at->format('H:i') }}</div>
                                                @endif
                                                @if ($attendance->marked_via === 'whatsapp' && $attendance->marked_at)
                                                    <div class="small text-success"><i class="ri-whatsapp-line"></i> Diabsen pengajar via WhatsApp {{ $attendance->marked_at->format('H:i') }}</div>
                                                @endif
                                                @if ($attendance->response && $attendance->responded_at)
                                                    <div class="small {{ $attendance->response === \App\Models\ScheduleAttendance::RESPONSE_CONFIRMED ? 'text-success' : 'text-warning' }}">
                                                        <i class="ri-whatsapp-line"></i>
                                                        {{ $attendance->response === \App\Models\ScheduleAttendance::RESPONSE_CONFIRMED ? 'Konfirmasi hadir' : 'Izin' }}
                                                        via WhatsApp {{ $attendance->responded_at->translatedFormat('d M H:i') }}
                                                    </div>
                                                @endif
                                                @if ($attendance->replaces)
                                                    <div class="text-muted small">Pengganti sesi {{ $attendance->replaces->session?->start_at->translatedFormat('d M Y') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFill)
                                                    <div class="d-flex gap-2" role="group" aria-label="Kehadiran {{ $attendance->student?->name }}">
                                                        @foreach ([\App\Models\ScheduleAttendance::STATUS_PRESENT => 'success', \App\Models\ScheduleAttendance::STATUS_ABSENT => 'danger', \App\Models\ScheduleAttendance::STATUS_EXCUSED => 'warning'] as $status => $color)
                                                            <input type="radio" class="btn-check" name="statuses[{{ $attendance->student_id }}]" value="{{ $status }}" id="st-{{ $attendance->id }}-{{ $status }}"
                                                                @checked(old('statuses.'.$attendance->student_id, $attendance->status) === $status) required
                                                                @if ($status === \App\Models\ScheduleAttendance::STATUS_PRESENT && $attendance->student) data-qr="{{ $attendance->student->qrCode() }}" data-name="{{ $attendance->student->name }}" @endif>
                                                            <label class="btn btn-sm rounded-pill px-3 btn-outline-{{ $color }}" for="st-{{ $attendance->id }}-{{ $status }}">{{ \App\Models\ScheduleAttendance::STATUS_LABELS[$status] }}</label>
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
                                                <td class="text-end small">{!! $secret($attendance->teacher_fee) !!}</td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted">Sesi ini belum punya murid. Tambahkan murid di menu Jadwal.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($canFill && $session->attendances->isNotEmpty())
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
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
                            <h6>Pindahkan Pertemuan Ini</h6>
                            <p class="small text-muted">Hanya pertemuan ini yang pindah &mdash; minggu lain tetap, dan jatah pertemuan bulannya tidak berubah.</p>
                            @include('schedule.sessions._slot-form', ['action' => route($routes.'.sessions.move', $session), 'method' => 'PUT', 'button' => 'Pindahkan', 'default' => $session, 'openings' => $openings])
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
                                {{-- Sesi pengganti boleh keluar dari minggu ini, jadi tanggalnya tetap diisi bebas. --}}
                                @include('schedule.sessions._slot-form', ['action' => route($routes.'.sessions.makeup', [$session, $attendance]), 'method' => 'POST', 'button' => 'Buat Sesi Pengganti', 'default' => null, 'openings' => null])
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Ikon mata: tampilkan / sembunyikan nominal (bawaan tersembunyi).
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-secret-toggle]');
            if (!button) return;
            const value = button.parentElement.querySelector('[data-secret]');
            const shown = value.textContent !== 'xxxxxxx';
            value.textContent = shown ? 'xxxxxxx' : value.dataset.secret;
            button.querySelector('i').className = shown ? 'ri-eye-off-line' : 'ri-eye-line';
            button.title = shown ? 'Tampilkan nominal' : 'Sembunyikan nominal';
        });
    </script>
@endpush
