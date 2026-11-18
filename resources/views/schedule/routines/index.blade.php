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

            {{-- Papan mingguan: tab ruangan, di dalamnya tab Minggu 1-5. Isinya kartu yang disusun
                 ke bawah per hari, bukan tabel tujuh kolom -- supaya lega dan terbaca di layar kecil.
                 Kartu berada di hari TANGGAL SESINYA, jadi pertemuan yang dipindah ikut berpindah
                 kartunya, dengan keterangan asalnya. --}}
            @if ($board->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="ri-calendar-2-line fs-1 d-block mb-2 opacity-50"></i>
                    <div class="fw-semibold text-body">{{ $filtered ? 'Tidak ada jadwal yang cocok.' : 'Belum ada jadwal di '.$month->translatedFormat('F Y') }}</div>
                    @unless ($filtered)
                        <div class="small mt-1">Atur jam bukanya di menu Grade, nanti jadwalnya muncul di sini.</div>
                    @endunless
                </div>
            @else
                <div class="alert alert-light border small py-2 mb-3">
                    Tiap pertemuan berdiri sendiri. Tombol <strong>pensil</strong> mengubah <strong>pertemuan itu saja</strong> &mdash; minggu lain tetap.
                </div>

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
                                        <button class="nav-link {{ $week === 1 ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#room-{{ $i }}-w{{ $week }}" type="button" role="tab">
                                            Minggu {{ $week }}
                                            @if ($group['weeks'][$week]->isNotEmpty())
                                                <span class="badge bg-light text-body ms-1">{{ $group['weeks'][$week]->flatten(1)->count() }}</span>
                                            @endif
                                        </button>
                                    </li>
                                @endfor
                            </ul>

                            <div class="tab-content">
                                @for ($week = 1; $week <= 5; $week++)
                                    <div class="tab-pane fade {{ $week === 1 ? 'show active' : '' }}" id="room-{{ $i }}-w{{ $week }}" role="tabpanel">
                                        @forelse ($group['weeks'][$week] as $day => $entries)
                                            <div class="d-flex align-items-baseline gap-2 border-bottom pb-1 mb-2 {{ $loop->first ? '' : 'mt-4' }}">
                                                <span class="fw-semibold">{{ \App\Models\ScheduleRoutine::DAYS[$day] ?? '-' }}</span>
                                                <span class="text-muted small">{{ $entries->first()['date']->translatedFormat('d F Y') }}</span>
                                                <span class="text-muted small ms-auto">{{ $entries->count() }} jadwal</span>
                                            </div>

                                            @foreach ($entries as $entry)
                                                @php($slot = $entry['routine'])
                                                @php($session = $entry['session'])
                                                <div class="border rounded-3 p-3 mb-2 d-flex flex-wrap align-items-center gap-3 {{ $session ? 'bg-success-subtle' : 'bg-light' }}">
                                                    <div class="text-center flex-shrink-0" style="min-width: 6rem">
                                                        <div class="fw-bold">{{ str_replace(':', '.', $entry['time']) }}</div>
                                                        <div class="text-muted" style="font-size: .75rem">{{ $slot->duration_minutes }} menit</div>
                                                    </div>

                                                    <div class="flex-grow-1" style="min-width: 14rem">
                                                        <div class="fw-semibold">{{ $slot->grade?->name ?? '-' }}
                                                            <span class="text-muted fw-normal small">&middot; {{ $slot->grade?->category?->subject?->name }} &rsaquo; {{ $slot->teacher?->name }}</span>
                                                        </div>
                                                        <div class="small mt-1">
                                                            @if ($slot->students->isEmpty())
                                                                <span class="text-muted">Belum ada murid</span>
                                                            @else
                                                                {{ $slot->students->pluck('name')->join(', ') }}@if ($slot->isGroup()) <span class="text-muted">({{ $slot->students->count() }}/{{ $slot->capacity }})</span>@endif
                                                            @endif
                                                        </div>
                                                        @if ($entry['from'])
                                                            <div class="text-warning-emphasis fw-semibold small mt-1"><i class="ri-arrow-right-up-line"></i> Dipindah dari {{ $entry['from'] }}</div>
                                                        @endif
                                                        @if ($entry['reason'])
                                                            <div class="text-muted small mt-1">{{ $entry['reason'] }}</div>
                                                        @endif
                                                    </div>

                                                    @if ($canManage)
                                                        {{-- Pensil mengubah pertemuan minggu ini saja. Isi Murid & Hapus hanya untuk
                                                             slot yang belum berisi murid, supaya kelas berjalan tidak salah pencet. --}}
                                                        <div class="d-flex gap-2 flex-shrink-0">
                                                            <a href="{{ route($routes.'.sessions.index', ['routine' => $slot->id]) }}" class="btn btn-sm btn-light border" title="Semua pertemuan slot ini"><i class="ri-checkbox-multiple-line"></i></a>
                                                            @if ($slot->students->isEmpty())
                                                                <a href="{{ route($routes.'.routines.fill', ['grade' => $slot->grade_id, 'month' => $month->format('Y-m')]) }}" class="btn btn-sm btn-light border" title="Isi murid"><i class="ri-user-add-line"></i></a>
                                                            @endif
                                                            @if ($session)
                                                                <a href="{{ route($routes.'.sessions.show', $session->id) }}" class="btn btn-sm btn-light border" title="Ubah pertemuan ini saja"><i class="ri-edit-line"></i></a>
                                                            @elseif ($slot->students->isEmpty())
                                                                <a href="{{ route($routes.'.routines.edit', $slot) }}" class="btn btn-sm btn-light border" title="Ubah slot jam ini"><i class="ri-edit-line"></i></a>
                                                            @endif
                                                            @if ($slot->students->isEmpty())
                                                                <form action="{{ route($routes.'.routines.destroy', $slot) }}" method="POST"
                                                                    onsubmit="return confirm('Hapus slot jam ini? Hanya slot yang belum ada muridnya yang bisa dihapus.');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-light border text-danger" title="Hapus slot"><i class="ri-delete-bin-line"></i></button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @empty
                                            <div class="text-center text-muted py-5">
                                                <i class="ri-calendar-line fs-3 d-block mb-2 opacity-50"></i>
                                                Tidak ada jadwal di minggu ini.
                                            </div>
                                        @endforelse
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
