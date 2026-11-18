@extends('layouts.dashboard')

@section('title', 'Isi Murid')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0">Isi Murid · {{ $grade->label() }}</h5>
                <div class="text-muted small">Pilih muridnya dulu, lalu centang jam yang dia ambil. Jam yang sudah penuh otomatis tidak bisa dicentang.</div>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="grade" value="{{ $grade->id }}">
                <label for="month" class="text-muted small text-nowrap mb-0">Bulan</label>
                <input type="month" id="month" name="month" class="form-control" style="width: 11rem" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
            </form>
        </div>
        <div class="card-body">
            @if ($routines->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="ri-time-line fs-1 d-block mb-2 opacity-50"></i>
                    <div class="fw-semibold text-body">Belum ada slot di {{ $month->translatedFormat('F Y') }}</div>
                    <div class="small mt-1">Isi jam bukanya dulu di form grade, nanti muridnya tinggal dicentang di sini.</div>
                    <a href="{{ route($routes.'.grades.edit', $grade->id) }}#pengajar" class="btn btn-primary btn-sm mt-3"><i class="ri-time-line me-1"></i>Atur Jam Buka</a>
                </div>
            @elseif ($students->isEmpty())
                <div class="text-muted">Belum ada murid di branch ini. Tambahkan dulu di tab Murid, atau lewat link pendaftaran portal.</div>
            @else
                <form method="POST" action="{{ route($routes.'.routines.fill.store') }}" id="fill-form">
                    @csrf
                    <input type="hidden" name="grade_id" value="{{ $grade->id }}">
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Murid</label>
                            <select id="student_id" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required data-student>
                                <option value="">-- Pilih murid --</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}" @selected(old('student_id') === $student->id)>{{ $student->name }}{{ $student->status === 'active' ? '' : ' (tidak aktif)' }}</option>
                                @endforeach
                            </select>
                            @error('student_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small" data-hint>Pilih murid dulu untuk melihat jam mana yang masih bisa diambil.</div>
                        </div>
                    </div>

                    {{-- Jam dikelompokkan per hari supaya daftarnya tidak memanjang ke bawah. Tab yang
                         tidak aktif hanya disembunyikan, centangnya tetap ikut terkirim saat Simpan. --}}
                    @php($byDay = $routines->groupBy('day_of_week'))

                    <ul class="nav nav-pills gap-2 mb-3" role="tablist">
                        @foreach ($byDay as $day => $daySlots)
                            <li class="nav-item">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#fill-day-{{ $day }}" type="button" role="tab">
                                    {{ \App\Models\ScheduleRoutine::DAYS[$day] ?? '-' }}
                                    <span class="badge bg-white text-body ms-1">{{ $daySlots->count() }}</span>
                                    <span class="badge bg-primary ms-1 d-none" data-day-taken="{{ $day }}"></span>
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content">
                        @foreach ($byDay as $day => $daySlots)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="fill-day-{{ $day }}" role="tabpanel">
                                <div class="list-group">
                                    @foreach ($daySlots as $routine)
                                        @php($names = $routine->students->pluck('name'))
                                        @php($full = $names->count() >= $routine->capacity)
                                        <label class="list-group-item d-flex align-items-center gap-3" data-slot data-day="{{ $day }}"
                                            data-students="{{ $routine->students->pluck('id')->join(',') }}" data-capacity="{{ $routine->capacity }}">
                                            <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="routine_ids[]" value="{{ $routine->id }}" disabled>
                                            <span class="flex-grow-1">
                                                <span class="fw-semibold">{{ $routine->timeRange() }}</span>
                                                <span class="d-block text-muted small">{{ $routine->teacher?->name }} · {{ $routine->room?->name }}</span>
                                            </span>
                                            <span class="text-end small text-nowrap">
                                                @if ($names->isEmpty())
                                                    <span class="badge bg-success-subtle text-success">Kosong</span>
                                                @elseif ($routine->capacity > 1)
                                                    <span class="badge {{ $full ? 'bg-secondary-subtle text-secondary' : 'bg-info-subtle text-info' }}">{{ $names->count() }}/{{ $routine->capacity }} terisi</span>
                                                    <span class="d-block text-muted">{{ $names->join(', ') }}</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary">Terisi</span>
                                                    <span class="d-block text-muted">{{ $names->first() }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route($routes.'.routines.index', ['month' => $month->format('Y-m')]) }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary" data-submit disabled>Simpan</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('fill-form');
            if (!form) return;

            const student = form.querySelector('[data-student]');
            const slots = [...form.querySelectorAll('[data-slot]')];
            const hint = form.querySelector('[data-hint]');
            const submit = form.querySelector('[data-submit]');

            // Slot penuh dimatikan, KECUALI kalau murid ini memang sudah ada di dalamnya -- supaya
            // mengeluarkan murid dari slot yang penuh tetap bisa dilakukan.
            const apply = () => {
                const id = student.value;
                let open = 0;
                let taken = 0;

                slots.forEach(slot => {
                    const ids = (slot.dataset.students || '').split(',').filter(Boolean);
                    const capacity = Number(slot.dataset.capacity) || 1;
                    const has = ids.includes(id);
                    const input = slot.querySelector('input');

                    input.checked = has;
                    input.disabled = !id || (ids.length >= capacity && !has);
                    slot.classList.toggle('opacity-50', input.disabled);
                    if (has) taken++;
                    else if (!input.disabled) open++;
                });

                // Lencana di tab hari: berapa jam yang diambil murid ini di hari itu.
                document.querySelectorAll('[data-day-taken]').forEach(badge => {
                    const count = slots.filter(slot => slot.dataset.day === badge.dataset.dayTaken && slot.querySelector('input').checked).length;
                    badge.textContent = count;
                    badge.classList.toggle('d-none', count === 0);
                });

                submit.disabled = !id;
                hint.textContent = !id
                    ? 'Pilih murid dulu untuk melihat jam mana yang masih bisa diambil.'
                    : `${taken} jam diambil murid ini · ${open} jam lain masih bisa dipilih.`;
            };

            student.addEventListener('change', apply);
            apply();
        })();
    </script>
@endpush
