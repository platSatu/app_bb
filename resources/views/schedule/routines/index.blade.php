@extends('layouts.dashboard')

@section('title', 'Jadwal Rutin')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($filtered = request()->hasAny(['search', 'day', 'status', 'teacher', 'student', 'grade']))
@php($next = $month->copy()->addMonthNoOverflow())

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-body">
            {{-- Baris atas: bulan + aksi bulanan (rekap, salin semua ke bulan berikutnya, buat jadwal). --}}
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <form method="GET" class="d-flex align-items-center gap-2 me-auto">
                    @foreach (['teacher', 'student', 'grade', 'search', 'day', 'status'] as $keep)
                        @if (request($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
                    @endforeach
                    <label for="month" class="text-muted small text-nowrap mb-0">Bulan</label>
                    <input type="month" id="month" name="month" class="form-control" style="width: 11rem" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                </form>
                <a href="{{ route($routes.'.routines.recap', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-secondary"><i class="ri-file-list-3-line me-1"></i>Rekap {{ $month->translatedFormat('F') }}</a>
                @if ($canManage)
                    <form method="POST" action="{{ route($routes.'.routines.duplicate-month', ['month' => $month->format('Y-m')]) }}"
                        onsubmit="this.querySelector('button').disabled = true; return confirm('Salin semua jadwal aktif {{ $month->translatedFormat('F Y') }} ke {{ $next->translatedFormat('F Y') }}? Hanya murid yang masih aktif yang ikut.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary"><i class="ri-file-copy-line me-1"></i>Salin ke {{ $next->translatedFormat('F') }}</button>
                    </form>
                @endif
                @if ($canManage && ! $isAdmin)
                    {{-- Jadwal dibuat dari form Grade (Pengajar & Jam), bukan dari sini. --}}
                    <a href="{{ route($routes.'.grades.index') }}" class="btn btn-primary"><i class="ri-time-line me-1"></i>Atur Jam di Grade</a>
                @endif
            </div>

            {{-- Baris kedua: cari & saring. --}}
            <form method="GET" class="row g-2 align-items-center mb-3">
                @foreach (['teacher', 'student', 'grade'] as $keep)
                    @if (request($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
                @endforeach
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
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
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line me-1"></i>Cari</button>
                    @if ($filtered)
                        <a href="{{ url()->current().'?month='.$month->format('Y-m') }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            {{-- Papan mingguan: tab ruangan, di dalamnya tab Minggu 1-5. Baris jam, kolom Senin-Minggu
                 (selalu tujuh kolom supaya posisi harinya tetap). Tiap kotak memakai tanggal nyata
                 minggu itu; minggu tanpa pertemuan tampil kosong apa adanya. --}}
            @if ($board->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="ri-calendar-2-line fs-1 d-block mb-2 opacity-50"></i>
                    <div class="fw-semibold text-body">{{ $filtered ? 'Tidak ada jadwal yang cocok.' : 'Belum ada jadwal di '.$month->translatedFormat('F Y') }}</div>
                    @unless ($filtered)
                        <div class="small mt-1">Atur jam bukanya di menu Grade, nanti jadwalnya muncul di sini.</div>
                    @endunless
                </div>
            @else
                <ul class="nav nav-pills gap-2 mb-3" role="tablist">
                    @foreach ($board as $i => $group)
                        <li class="nav-item">
                            <button class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#room-{{ $i }}" type="button" role="tab">
                                <i class="ri-building-line me-1"></i>{{ $group['room']?->name ?? 'Tanpa ruangan' }}
                                <span class="badge bg-white text-body ms-1">{{ $group['count'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content">
                    @foreach ($board as $i => $group)
                        <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="room-{{ $i }}" role="tabpanel">
                            <ul class="nav nav-tabs mb-3" role="tablist">
                                @for ($week = 1; $week <= 5; $week++)
                                    <li class="nav-item">
                                        <button class="nav-link {{ $week === 1 ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#room-{{ $i }}-w{{ $week }}" type="button" role="tab">Minggu {{ $week }}</button>
                                    </li>
                                @endfor
                            </ul>

                            <div class="tab-content">
                                @for ($week = 1; $week <= 5; $week++)
                                    <div class="tab-pane fade {{ $week === 1 ? 'show active' : '' }}" id="room-{{ $i }}-w{{ $week }}" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-bordered align-middle mb-0" style="table-layout: fixed">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 8.5rem">Jam</th>
                                                        @foreach (\App\Models\ScheduleRoutine::DAYS as $value => $label)
                                                            <th class="text-center small">{{ $label }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($group['times'] as $time)
                                                        <tr>
                                                            <td class="text-nowrap fw-semibold small">{{ str_replace('–', ' s/d ', $time['label']) }}</td>
                                                            @foreach (\App\Models\ScheduleRoutine::DAYS as $day => $label)
                                                                @php($slots = $group['cells'][$day.'@'.$time['value']] ?? collect())
                                                                <td class="p-1 align-top">
                                                                    @forelse ($slots as $slot)
                                                                        @php($session = $sessions[$slot->id.'@'.$week] ?? null)
                                                                        <div class="border rounded p-2 {{ $loop->last ? '' : 'mb-1' }} {{ $slot->students->isEmpty() ? 'bg-light' : 'bg-success-subtle' }} {{ $session ? '' : 'opacity-50' }}">
                                                                            <div class="small fw-semibold text-truncate">{{ $slot->grade?->name ?? '-' }}</div>
                                                                            <div class="text-muted text-truncate" style="font-size: .75rem">{{ $slot->grade?->category?->subject?->name }} › {{ $slot->teacher?->name }}</div>
                                                                            <div class="small text-truncate mt-1">
                                                                                @if ($slot->students->isEmpty())
                                                                                    <span class="text-muted">Belum ada murid</span>
                                                                                @else
                                                                                    {{ $slot->students->pluck('name')->join(', ') }}@if ($slot->isGroup()) <span class="text-muted">({{ $slot->students->count() }}/{{ $slot->capacity }})</span>@endif
                                                                                @endif
                                                                            </div>
                                                                            <div class="text-muted mt-1" style="font-size: .72rem">
                                                                                {{ $session ? \Carbon\Carbon::parse($session->date)->translatedFormat('d M') : 'Tidak ada pertemuan' }}
                                                                            </div>
                                                                            @if ($canManage)
                                                                                <div class="d-flex gap-1 mt-2">
                                                                                    <a href="{{ route($routes.'.sessions.index', ['routine' => $slot->id, 'date_from' => today()->toDateString()]) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Sesi & absensi"><i class="ri-checkbox-multiple-line"></i></a>
                                                                                    <a href="{{ route($routes.'.routines.fill', ['grade' => $slot->grade_id, 'month' => $month->format('Y-m')]) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Isi murid"><i class="ri-user-add-line"></i></a>
                                                                                    <a href="{{ route($routes.'.routines.edit', $slot) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Ubah jadwal"><i class="ri-edit-line"></i></a>
                                                                                    <form action="{{ route($routes.'.routines.destroy', $slot) }}" method="POST" class="flex-fill d-flex"
                                                                                        onsubmit="return confirm('Hapus slot ini? Sesi yang belum berjalan ikut terhapus.');">
                                                                                        @csrf
                                                                                        @method('DELETE')
                                                                                        <button type="submit" class="btn btn-sm btn-light border w-100 p-1 text-danger" title="Hapus slot"><i class="ri-delete-bin-line"></i></button>
                                                                                    </form>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @empty
                                                                        <div class="text-center text-muted py-2" style="font-size: .75rem">—</div>
                                                                    @endforelse
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
