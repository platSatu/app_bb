@extends('layouts.dashboard')

@section('title', 'Jadwal Rutin')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($filtered = request()->hasAny(['search', 'day', 'status', 'teacher', 'student', 'grade']))

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                @foreach (['teacher', 'student', 'grade'] as $keep)
                    @if (request($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
                @endforeach
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama pengajar, murid, atau grade" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-2">
                    <select name="day" class="form-select">
                        <option value="">Semua hari</option>
                        @foreach (\App\Models\ScheduleRoutine::DAYS as $value => $label)
                            <option value="{{ $value }}" @selected((string) request('day') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\ScheduleRoutine::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if ($filtered)
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @if ($canManage && ! $isAdmin)
                <div class="d-flex justify-content-end mb-3">
                    <a href="{{ route($routes.'.routines.create', array_filter(['grade' => request('grade')])) }}" class="btn btn-primary"><i class="ri-add-line"></i> Buat Jadwal</a>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Hari & Jam</th>
                            <th>Grade</th>
                            <th>Pengajar</th>
                            <th>Ruangan</th>
                            <th>Murid</th>
                            <th>Berlaku</th>
                            @if ($isAdmin) <th>Perusahaan / Branch</th> @endif
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td class="text-nowrap">
                                    <div class="fw-semibold">{{ $item->dayLabel() }}</div>
                                    <div class="text-muted small">{{ $item->timeRange() }}</div>
                                </td>
                                <td>{{ $item->grade?->label() }}</td>
                                <td>{{ $item->teacher?->name ?? '-' }}</td>
                                <td>{{ $item->room?->name ?? '-' }}</td>
                                <td class="small">
                                    <span class="badge {{ $item->isGroup() ? 'bg-primary-subtle text-primary' : 'bg-light text-dark border' }}">{{ $item->isGroup() ? 'Grup '.$item->students->count().'/'.$item->capacity : 'Privat' }}</span>
                                    <div>{{ $item->students->pluck('name')->join(', ') ?: 'Belum ada murid' }}</div>
                                </td>
                                <td class="small text-nowrap">
                                    {{ $item->starts_on->translatedFormat('d M Y') }} – {{ $item->ends_on?->translatedFormat('d M Y') ?? 'seterusnya' }}
                                </td>
                                @if ($isAdmin)
                                    <td>
                                        <div>{{ $item->branch?->company?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $item->branch?->name }}</div>
                                    </td>
                                @endif
                                <td><span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route($routes.'.sessions.index', ['routine' => $item->id, 'date_from' => today()->toDateString()]) }}" class="btn btn-sm rounded-pill btn-outline-success">
                                        <i class="ri-checkbox-multiple-line"></i> Sesi ({{ $item->upcoming_count }})
                                    </a>
                                    @if ($canManage)
                                        <a href="{{ route($routes.'.routines.edit', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <form action="{{ route($routes.'.routines.destroy', $item) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Hapus jadwal ini? Sesi yang belum berjalan ikut terhapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 9 : 8 }}" class="text-center text-muted py-4">
                                    {{ $filtered ? 'Tidak ada jadwal yang cocok.' : 'Belum ada jadwal. Buat lewat tombol + Jadwal di menu Grade, atau tombol Buat Jadwal.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
