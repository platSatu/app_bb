@extends('layouts.dashboard')

@section('title', 'Jadwal')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($filtered = request()->hasAny(['search', 'day', 'status', 'teacher', 'student', 'grade']))
@php($next = $month->copy()->addMonthNoOverflow())
{{-- Urutan kolom papan seperti kalender laptop: Minggu dulu (7 = Minggu, 1 = Senin, ...). --}}
@php($weekDays = [7, 1, 2, 3, 4, 5, 6])

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
                 kolomnya selalu Minggu-Sabtu (seperti kalender laptop) supaya posisi harinya tidak bergeser. Kartu diletakkan
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
                                {{-- Minggu kalender Minggu-Sabtu; Minggu 1 = minggu yang memuat tanggal 1. Tanggal bulan lain tampil tapi nonaktif. --}}
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
                                            {{-- Lebar minimum: di layar sempit papan digeser ke samping, bukan dijepit sampai teks & tombol berantakan. --}}
                                            <table class="table table-bordered align-middle mb-0" style="table-layout: fixed; min-width: 1120px">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 7.5rem">Jam</th>
                                                        @foreach ($weekDays as $day)
                                                            @php($label = \App\Models\ScheduleRoutine::DAYS[$day])
                                                            @php($headDate = $weekStart->copy()->addDays($day % 7))
                                                            {{-- Patokan tetap bulan ini: hari milik bulan lain tetap terlihat tanggalnya, tapi nonaktif. --}}
                                                            <th class="text-center small {{ $headDate->isSameMonth($month) ? '' : 'text-muted fw-normal opacity-50' }}">
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
                                                            @foreach ($weekDays as $day)
                                                                @if (! $weekStart->copy()->addDays($day % 7)->isSameMonth($month))
                                                                    {{-- Di luar bulan ini: nonaktif, tidak berisi jadwal. --}}
                                                                    <td class="p-1 bg-light" title="Di luar {{ $month->translatedFormat('F') }}" style="background-image: repeating-linear-gradient(135deg, transparent 0 8px, rgba(0,0,0,.03) 8px 16px)"></td>
                                                                    @continue
                                                                @endif
                                                                <td class="p-1 align-top">
                                                                    @forelse (($entries[$day] ?? collect())->where('time', $time) as $entry)
                                                                        @php($slot = $entry['routine'])
                                                                        @php($session = $entry['session'])
                                                                        {{-- Kartu: hijau = pertemuan terisi, kuning = jam kosong (belum ada murid), abu = jatah bulan ini sudah terpakai / lewat.
                                                                             Warna ditulis langsung supaya tidak berubah oleh tema; teks selalu gelap agar mudah dibaca. --}}
                                                                        @php($empty = $slot->students->isEmpty())
                                                                        @php([$bg, $edge, $chip, $chipText] = $session ? ['#e3f6ea', '#2e9e5b', '#c8eed5', 'Terisi'] : ($empty ? ['#fff6d6', '#e0a106', '#ffe69c', 'Kosong'] : ['#f3f4f6', '#adb5bd', '#e5e7eb', 'Cadangan']))
                                                                        <div class="rounded-3 p-2 shadow-sm {{ $loop->last ? '' : 'mb-2' }}" style="background:{{ $bg }}; border:1px solid {{ $edge }}40; border-left:4px solid {{ $edge }}; color:#212529">
                                                                            <div class="d-flex align-items-start gap-1">
                                                                                <div class="flex-grow-1 text-truncate lh-sm" title="{{ $slot->grade?->label() }}">
                                                                                    <span class="d-block text-uppercase" style="font-size:.6rem; letter-spacing:.05em; color:#6c757d">Grade</span>
                                                                                    <span class="fw-semibold small">{{ $slot->grade?->name ?? '-' }}</span>
                                                                                </div>
                                                                                <span class="badge rounded-pill fw-medium" style="background:{{ $chip }}; color:#212529; font-size:.65rem">{{ $chipText }}</span>
                                                                            </div>
                                                                            <div class="text-truncate" style="font-size:.75rem; color:#495057" title="{{ $slot->grade?->category?->subject?->name }} › {{ $slot->teacher?->name }}">
                                                                                <i class="ri-music-2-line"></i> {{ $slot->grade?->category?->subject?->name }} &middot; <i class="ri-user-star-line"></i> {{ $slot->teacher?->name }}
                                                                            </div>
                                                                            <div class="mt-1 text-truncate" style="font-size:.8rem" title="{{ $slot->students->pluck('name')->join(', ') ?: 'Belum ada murid' }}">
                                                                                @if ($empty)
                                                                                    <span style="color:#7a5a00"><i class="ri-user-unfollow-line"></i> Belum ada murid</span>
                                                                                @else
                                                                                    <span class="fw-semibold"><i class="ri-group-line"></i> {{ $slot->students->pluck('name')->join(', ') }}</span>@if ($slot->isGroup()) <span style="color:#495057">({{ $slot->students->count() }}/{{ $slot->capacity }})</span>@endif
                                                                                @endif
                                                                            </div>
                                                                            {{-- Baris keterangan selalu ada (kosong pun tetap memakan tempat) & satu baris, supaya tinggi semua kartu sama rata. --}}
                                                                            @php($note = $entry['from'] ? 'Dipindah dari '.$entry['from'] : ($empty ? '' : $entry['reason']))
                                                                            <div class="mt-1 text-truncate {{ $entry['from'] ? 'fw-semibold' : '' }}" style="font-size:.72rem; line-height:1.1rem; height:1.1rem; color:{{ $entry['from'] ? '#8a4b00' : '#495057' }}" title="{{ $note }}">
                                                                                @if ($entry['from'])<i class="ri-arrow-right-up-line"></i>@endif {{ $note }}
                                                                            </div>
                                                                            @if ($canManage)
                                                                                {{-- Tombol ikon bundar; keterangannya muncul saat kursor diarahkan (title) & dibaca pembaca layar (aria-label).
                                                                                     Pensil mengubah pertemuan di kotak ini saja. Isi Murid & Hapus hanya untuk jam yang belum berisi murid. --}}
                                                                                @php($btn = 'btn btn-sm bg-white border rounded-circle d-inline-flex align-items-center justify-content-center p-0')
                                                                                @php($size = 'width:28px; height:28px')
                                                                                {{-- Satu baris, kolom sama lebar: semua tombol (termasuk hapus) selalu sejajar, berapa pun lebar kartunya. --}}
                                                                                <div class="mt-2 pt-2" style="border-top:1px dashed {{ $edge }}55; display:grid; grid-auto-flow:column; grid-auto-columns:minmax(0, 1fr); gap:4px; justify-items:center">
                                                                                    <a href="{{ route($routes.'.sessions.index', ['routine' => $slot->id]) }}" class="{{ $btn }}" style="{{ $size }}" title="Lihat semua pertemuan jam ini" aria-label="Lihat semua pertemuan jam ini"><i class="ri-calendar-schedule-line"></i></a>
                                                                                    @if ($empty)
                                                                                        <a href="{{ route($routes.'.routines.fill', ['grade' => $slot->grade_id, 'month' => $slot->starts_on->format('Y-m')]) }}" class="{{ $btn }}" style="{{ $size }}" title="Isi murid ke jam ini" aria-label="Isi murid ke jam ini"><i class="ri-user-add-line"></i></a>
                                                                                    @endif
                                                                                    @if ($session)
                                                                                        {{-- Langsung ke bagian Absensi pertemuan ini. --}}
                                                                                        <a href="{{ route($routes.'.sessions.show', $session->id) }}#absensi" class="{{ $btn }}" style="{{ $size }}" title="Isi absensi pertemuan ini" aria-label="Isi absensi pertemuan ini"><i class="ri-user-follow-line"></i></a>
                                                                                        <a href="{{ route($routes.'.sessions.show', $session->id) }}" class="{{ $btn }}" style="{{ $size }}" title="Ubah pertemuan ini saja (pindah, batal, pengganti)" aria-label="Ubah pertemuan ini saja"><i class="ri-pencil-line"></i></a>
                                                                                    @elseif ($empty)
                                                                                        <a href="{{ route($routes.'.routines.edit', $slot) }}" class="{{ $btn }}" style="{{ $size }}" title="Ubah hari / jam slot ini" aria-label="Ubah hari atau jam slot ini"><i class="ri-pencil-line"></i></a>
                                                                                    @endif
                                                                                    @if ($empty)
                                                                                        <form action="{{ route($routes.'.routines.destroy', $slot) }}" method="POST" class="m-0"
                                                                                            onsubmit="return confirm('Hapus jam ini? Hanya jam yang belum ada muridnya yang bisa dihapus.');">
                                                                                            @csrf
                                                                                            @method('DELETE')
                                                                                            <button type="submit" class="{{ $btn }} text-danger" style="{{ $size }}" title="Hapus slot jam ini" aria-label="Hapus slot jam ini"><i class="ri-delete-bin-6-line"></i></button>
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
