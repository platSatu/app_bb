@extends('layouts.dashboard')

@section('title', 'Sesi & Absensi')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end mb-3">
                @foreach (['routine', 'student'] as $keep)
                    @if (request($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
                @endforeach
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $from?->format('Y-m-d') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $to?->format('Y-m-d') }}">
                </div>
                @if ($teachers->isNotEmpty())
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Pengajar</label>
                        <select name="teacher" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected(request('teacher') === $teacher->id)>{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($rooms->isNotEmpty())
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Ruangan</label>
                        <select name="room" class="form-select">
                            <option value="">Semua</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected(request('room') === $room->id)>{{ $room->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\ScheduleSession::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['date_from', 'date_to', 'teacher', 'room', 'status', 'routine', 'student']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Kembali ke minggu ini"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jam</th>
                            <th>Grade</th>
                            <th>Pengajar</th>
                            <th>Ruangan</th>
                            <th>Murid</th>
                            @if ($isAdmin) <th>Perusahaan / Branch</th> @endif
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($lastDate = null)
                        @forelse ($items as $item)
                            @if ($lastDate !== $item->date->toDateString())
                                @php($lastDate = $item->date->toDateString())
                                <tr class="table-light">
                                    <td colspan="{{ $isAdmin ? 8 : 7 }}" class="fw-semibold small">
                                        {{ $item->date->translatedFormat('l, d F Y') }}
                                        @if ($item->date->isToday()) <span class="badge bg-primary ms-1">Hari ini</span> @endif
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td class="text-nowrap fw-semibold">{{ $item->timeRange() }}</td>
                                <td>
                                    {{ $item->grade?->label() }}
                                    @if ($item->isMakeup()) <span class="badge bg-warning-subtle text-warning">Pengganti</span> @endif
                                    @if ($item->moved_at) <span class="badge bg-info-subtle text-info">Dipindah</span> @endif
                                </td>
                                <td>{{ $item->teacher?->name ?? '-' }}</td>
                                <td>{{ $item->room?->name ?? '-' }}</td>
                                <td class="small">
                                    @foreach ($item->attendances as $attendance)
                                        <span class="badge {{ $attendance->statusBadge() }} mb-1" title="{{ $attendance->statusLabel() }}">{{ $attendance->student?->name }}</span>
                                    @endforeach
                                    @if ($item->attendances->isEmpty()) <span class="text-muted">Belum ada murid</span> @endif
                                </td>
                                @if ($isAdmin)
                                    <td>
                                        <div>{{ $item->branch?->company?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $item->branch?->name }}</div>
                                    </td>
                                @endif
                                <td><span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route($routes.'.sessions.show', $item) }}" class="btn btn-sm rounded-pill {{ $item->status === 'scheduled' && $item->start_at->isPast() ? 'btn-warning' : 'btn-outline-secondary' }}">
                                        <i class="ri-checkbox-line"></i> {{ $item->status === 'scheduled' && $item->start_at->isPast() ? 'Isi Absensi' : 'Detail' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 8 : 7 }}" class="text-center text-muted py-4">Tidak ada sesi di rentang tanggal ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
