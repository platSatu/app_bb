@extends('layouts.dashboard')

@section('title', 'Isi Jam')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($inactive = fn ($model) => $model->status === 'active' ? '' : ' (tidak aktif)')
@php($monthly = $grades->reject(fn ($g) => $g->isPackage())->values())

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Isi Jam</h5>
            <div class="text-muted small">Tulis jam buka pengajar sekali, misalnya Senin 13.00–15.00. Jendelanya dipecah sendiri mengikuti durasi grade, dan tiap potongan jadi satu jadwal yang muridnya diisi belakangan.</div>
        </div>
        <div class="card-body">
            @if ($monthly->isEmpty())
                <div class="text-muted">Belum ada grade bulanan. Halaman ini untuk grade bulanan; grade paket memakai pola tanggal dan dibuat lewat <a href="{{ route($routes.'.routines.create') }}">Buat Jadwal Rutin</a>.</div>
            @else
                <form method="POST" action="{{ route($routes.'.routines.slots.store') }}" id="slot-form">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="grade_id" class="form-label">Grade</label>
                            <select id="grade_id" name="grade_id" class="form-select @error('grade_id') is-invalid @enderror" required>
                                <option value="">-- Pilih grade --</option>
                                @foreach ($monthly as $item)
                                    <option value="{{ $item->id }}" data-branch="{{ $item->branch_id }}" data-room="{{ $item->category?->subject?->room_id }}"
                                        data-duration="{{ $item->duration_minutes }}" data-teachers="{{ $item->teachers->pluck('id')->join(',') }}"
                                        @selected(old('grade_id', $grade?->id) === $item->id)>
                                        {{ $item->label() }} · {{ $item->duration_minutes }} menit{{ $monthly->pluck('branch_id')->unique()->count() > 1 ? ' — '.$item->branch?->name : '' }}{{ $inactive($item) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('grade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="teacher_id" class="form-label">Pengajar</label>
                            <select id="teacher_id" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                                <option value="">-- Pilih pengajar --</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" data-branch="{{ $teacher->branch_id }}" @selected(old('teacher_id') === $teacher->id)>{{ $teacher->name }}{{ $inactive($teacher) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Hanya pengajar yang ditugaskan di grade ini. Kalau kosong, tugaskan dulu lewat tombol Pengajar di tab Grade.</div>
                            @error('teacher_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="room_id" class="form-label">Ruangan</label>
                            <select id="room_id" name="room_id" class="form-select @error('room_id') is-invalid @enderror" required>
                                <option value="">-- Pilih ruangan --</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" data-branch="{{ $room->branch_id }}" @selected(old('room_id') === $room->id)>{{ $room->name }}{{ $inactive($room) }}</option>
                                @endforeach
                            </select>
                            @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="day_of_week" class="form-label">Hari</label>
                            <select id="day_of_week" name="day_of_week" class="form-select @error('day_of_week') is-invalid @enderror" required>
                                @foreach (\App\Models\ScheduleRoutine::DAYS as $value => $label)
                                    <option value="{{ $value }}" @selected((int) old('day_of_week', 1) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('day_of_week') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="month" class="form-label">Bulan</label>
                            <input type="month" id="month" name="month" class="form-control @error('month') is-invalid @enderror" value="{{ old('month', $month->format('Y-m')) }}" required>
                            <div class="form-text">Bulan berikutnya disalin lewat tombol Duplikat di daftar jadwal.</div>
                            @error('month') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="start_time" class="form-label">Jam Mulai</label>
                            <input type="time" id="start_time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time', '13:00') }}" required>
                            @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="end_time" class="form-label">Jam Selesai</label>
                            <input type="time" id="end_time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time', '15:00') }}" required>
                            @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="capacity" class="form-label">Kapasitas Murid per Slot</label>
                            <input type="number" id="capacity" name="capacity" min="1" max="100" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', 1) }}" required>
                            <div class="form-text">1 = privat. Lebih dari 1 = kelas grup.</div>
                            @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Slot yang Akan Terbentuk</label>
                            <div class="bg-light rounded p-3" data-preview></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route($routes.'.routines.index') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary" data-submit>Buat Slot</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('slot-form');
            if (!form) return;

            const grade = form.querySelector('#grade_id');
            const teacher = form.querySelector('#teacher_id');
            const room = form.querySelector('#room_id');
            const start = form.querySelector('#start_time');
            const end = form.querySelector('#end_time');
            const preview = form.querySelector('[data-preview]');
            const submit = form.querySelector('[data-submit]');
            const MAX = 24;

            const pad = (n) => String(n).padStart(2, '0');
            const toMinutes = (value) => {
                const [h, m] = (value || '').split(':').map(Number);
                return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
            };
            const label = (minutes) => pad(Math.floor(minutes / 60)) + '.' + pad(minutes % 60);

            // Pengajar dibatasi ke yang ditugaskan di grade ini; ruangan & isian lain mengikuti branch grade.
            const applyGrade = () => {
                const selected = grade.selectedOptions[0];
                const branch = selected?.dataset.branch;
                const allowed = (selected?.dataset.teachers || '').split(',').filter(Boolean);

                [...teacher.options].forEach(option => {
                    if (!option.value) return;
                    option.hidden = Boolean(branch) && !allowed.includes(option.value);
                    if (option.hidden && option.selected) teacher.value = '';
                });
                [...room.options].forEach(option => {
                    if (!option.value) return;
                    option.hidden = Boolean(branch) && option.dataset.branch !== branch;
                    if (option.hidden && option.selected) room.value = '';
                });
                if (selected?.dataset.room && !room.value) room.value = selected.dataset.room;
            };

            const update = () => {
                const duration = Number(grade.selectedOptions[0]?.dataset.duration) || 0;
                const from = toMinutes(start.value);
                const until = toMinutes(end.value);
                const times = [];

                if (duration > 0 && from !== null && until !== null) {
                    while (times.length < MAX && from + duration * (times.length + 1) <= until) {
                        times.push(from + duration * times.length);
                    }
                }

                submit.disabled = times.length === 0;

                if (!duration) {
                    preview.innerHTML = '<span class="text-muted">Pilih grade dulu — durasinya yang menentukan lebar tiap slot.</span>';
                    return;
                }

                if (times.length === 0) {
                    preview.innerHTML = `<span class="text-danger">Jendela jamnya belum cukup untuk satu pertemuan ${duration} menit. Mundurkan jam selesai.</span>`;
                    return;
                }

                const chips = times.map(t => `<span class="badge bg-white text-body border me-1 mb-1">${label(t)}</span>`).join('');
                const sisa = until - (from + duration * times.length);

                preview.innerHTML = `<div class="fw-semibold mb-2">Akan terbentuk ${times.length} slot · ${duration} menit per slot</div>${chips}`
                    + (sisa > 0 ? `<div class="text-muted small mt-2">Sisa ${sisa} menit di ujung tidak cukup untuk satu pertemuan, jadi tidak dibuatkan slot.</div>` : '')
                    + (times.length === MAX ? `<div class="text-muted small mt-2">Dibatasi ${MAX} slot sekali buat.</div>` : '');
            };

            grade.addEventListener('change', () => { applyGrade(); update(); });
            [start, end].forEach(el => el.addEventListener('input', update));
            applyGrade();
            update();
        })();
    </script>
@endpush
