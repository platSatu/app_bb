@extends('layouts.dashboard')

@section('title', 'Jadwal')
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
                <a href="{{ route($routes.'.routines.recap', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-secondary"><i class="ri-file-list-3-line me-1"></i>Laporan {{ $month->translatedFormat('F') }}</a>
                @if ($canManage)
                    {{-- Nonaktif kalau semua jadwal bulanan bulan ini sudah punya salinan di bulan berikutnya. --}}
                    @if ($copyPending > 0)
                        <form method="POST" action="{{ route($routes.'.routines.duplicate-month', ['month' => $month->format('Y-m')]) }}"
                            onsubmit="this.querySelector('button').disabled = true; return confirm('Salin {{ $copyPending }} jadwal aktif {{ $month->translatedFormat('F Y') }} ke {{ $next->translatedFormat('F Y') }}? Hanya murid yang masih aktif yang ikut.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary"><i class="ri-file-copy-line me-1"></i>Salin ke {{ $next->translatedFormat('F') }}</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled title="Semua jadwal {{ $month->translatedFormat('F') }} sudah ada di {{ $next->translatedFormat('F Y') }}">
                            <i class="ri-check-line me-1"></i>Sudah disalin ke {{ $next->translatedFormat('F') }}
                        </button>
                    @endif
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
                 kolomnya selalu Senin-Minggu supaya posisi harinya tidak bergeser. Kartu diletakkan
                 di kolom TANGGAL SESINYA, bukan menurut pola, jadi pertemuan yang dipindah ikut
                 berpindah kolomnya lengkap dengan keterangan asalnya. --}}
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
                                {{-- Minggu kalender Senin-Minggu apa adanya, termasuk tanggal bulan sebelum/sesudahnya. --}}
                                @foreach ($weekStarts as $week => $weekStart)
                                    @php($from = $weekStart->copy())
                                    @php($to = $weekStart->copy()->addDays(6))
                                    <li class="nav-item">
                                        <button class="nav-link {{ $week === $activeWeek ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#room-{{ $i }}-w{{ $week }}" type="button" role="tab">
                                            Minggu {{ $week }}
                                            <span class="d-block fw-normal text-muted" style="font-size: .7rem">{{ $from->isSameMonth($to) ? $from->format('d') : $from->translatedFormat('d M') }}–{{ $to->translatedFormat('d M') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="tab-content">
                                @foreach ($weekStarts as $week => $weekStart)
                                    @php($entries = $group['weeks'][$week])
                                    @php($times = $entries->flatten(1)->pluck('time')->unique()->sort()->values())
                                    <div class="tab-pane fade {{ $week === $activeWeek ? 'show active' : '' }}" id="room-{{ $i }}-w{{ $week }}" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-bordered align-middle mb-0" style="table-layout: fixed">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 8.5rem">Jam</th>
                                                        @foreach (\App\Models\ScheduleRoutine::DAYS as $day => $label)
                                                            @php($headDate = $weekStart->copy()->addDays($day - 1))
                                                            {{-- Hari milik bulan lain tetap ditampilkan (kalender sebenarnya), diberi warna lebih pudar. --}}
                                                            <th class="text-center small {{ $headDate->isSameMonth($month) ? '' : 'text-muted opacity-75' }}">
                                                                {{ $label }}
                                                                <span class="d-block fw-normal text-muted" style="font-size: .7rem">{{ $headDate->translatedFormat('d M') }}</span>
                                                            </th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($times as $time)
                                                        <tr>
                                                            @php($rowSlot = $entries->flatten(1)->firstWhere('time', $time)['routine'])
                                                            <td class="text-nowrap fw-semibold small">{{ str_replace(':', '.', $time) }} s/d {{ \Carbon\Carbon::createFromFormat('H:i', $time)->addMinutes($rowSlot->duration_minutes)->format('H.i') }}</td>
                                                            @foreach (\App\Models\ScheduleRoutine::DAYS as $day => $label)
                                                                <td class="p-1 align-top">
                                                                    @forelse (($entries[$day] ?? collect())->where('time', $time) as $entry)
                                                                        @php($slot = $entry['routine'])
                                                                        @php($session = $entry['session'])
                                                                        @php($otherMonth = ! $entry['date']->isSameMonth($month))
                                                                        <div class="border rounded p-2 {{ $loop->last ? '' : 'mb-1' }} {{ $session ? 'bg-success-subtle' : 'bg-light' }} {{ $otherMonth ? 'opacity-75' : '' }}">
                                                                            @if ($otherMonth)
                                                                                <div class="text-muted" style="font-size: .7rem"><i class="ri-calendar-line"></i> Jadwal {{ $entry['date']->translatedFormat('F') }}</div>
                                                                            @endif
                                                                            <div class="small fw-semibold text-truncate">{{ $slot->grade?->name ?? '-' }}</div>
                                                                            <div class="text-muted text-truncate" style="font-size: .75rem">{{ $slot->grade?->category?->subject?->name }} &rsaquo; {{ $slot->teacher?->name }}</div>
                                                                            <div class="small text-truncate mt-1">
                                                                                @if ($slot->students->isEmpty())
                                                                                    <span class="text-muted">Belum ada murid</span>
                                                                                @else
                                                                                    {{ $slot->students->pluck('name')->join(', ') }}@if ($slot->isGroup()) <span class="text-muted">({{ $slot->students->count() }}/{{ $slot->capacity }})</span>@endif
                                                                                @endif
                                                                            </div>
                                                                            @if ($entry['from'])
                                                                                <div class="text-warning-emphasis fw-semibold mt-1" style="font-size: .72rem"><i class="ri-arrow-right-up-line"></i> Dipindah dari {{ $entry['from'] }}</div>
                                                                            @elseif ($entry['reason'])
                                                                                <div class="text-muted mt-1" style="font-size: .72rem">{{ $entry['reason'] }}</div>
                                                                            @endif
                                                                            @if ($canManage)
                                                                                {{-- Pensil mengubah pertemuan di kotak ini saja. Isi Murid & Hapus hanya
                                                                                     untuk jam yang belum berisi murid. --}}
                                                                                <div class="d-flex gap-1 mt-2">
                                                                                    <a href="{{ route($routes.'.sessions.index', ['routine' => $slot->id]) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Semua pertemuan jam ini"><i class="ri-checkbox-multiple-line"></i></a>
                                                                                    @if ($slot->students->isEmpty())
                                                                                        <a href="{{ route($routes.'.routines.fill', ['grade' => $slot->grade_id, 'month' => $slot->starts_on->format('Y-m')]) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Isi murid"><i class="ri-user-add-line"></i></a>
                                                                                    @endif
                                                                                    @if ($session)
                                                                                        {{-- Langsung ke bagian Absensi pertemuan ini. --}}
                                                                                        <a href="{{ route($routes.'.sessions.show', $session->id) }}#absensi" class="btn btn-sm btn-light border flex-fill p-1" title="Absensi pertemuan ini"><i class="ri-user-follow-line"></i></a>
                                                                                        <a href="{{ route($routes.'.sessions.show', $session->id) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Ubah pertemuan ini saja"><i class="ri-edit-line"></i></a>
                                                                                    @elseif ($slot->students->isEmpty())
                                                                                        <a href="{{ route($routes.'.routines.edit', $slot) }}" class="btn btn-sm btn-light border flex-fill p-1" title="Ubah jam ini"><i class="ri-edit-line"></i></a>
                                                                                    @endif
                                                                                    @if ($slot->students->isEmpty())
                                                                                        <form action="{{ route($routes.'.routines.destroy', $slot) }}" method="POST" class="flex-fill d-flex"
                                                                                            onsubmit="return confirm('Hapus jam ini? Hanya jam yang belum ada muridnya yang bisa dihapus.');">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <button type="submit" class="btn btn-sm btn-light border w-100 p-1 text-danger" title="Hapus jam ini"><i class="ri-delete-bin-line"></i></button>
                                                                                        </form>
                                                                                    @endif
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @empty
                                                                        <div class="text-center text-muted py-2" style="font-size: .75rem">&mdash;</div>
                                                                    @endforelse
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @empty
                                                        <tr><td colspan="8" class="text-center text-muted small py-4">Tidak ada jadwal di minggu ini.</td></tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
