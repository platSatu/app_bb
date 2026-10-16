@extends('layouts.dashboard')

@section('title', $item->exists ? 'Edit Jadwal' : 'Buat Jadwal')
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($inactive = fn ($model) => $model->status === 'active' ? '' : ' (tidak aktif)')

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">{{ $item->exists ? 'Edit Jadwal Rutin' : 'Buat Jadwal Rutin' }}</h5>
            <div class="text-muted small">Sesi dibuat otomatis tiap minggu sesuai jumlah pertemuan per bulan di grade. Pengajar, ruangan, dan murid yang bentrok jamnya akan ditolak.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $item->exists ? route($routes.'.routines.update', $item) : route($routes.'.routines.store') }}" id="routine-form">
                @csrf
                @if ($item->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="grade_id" class="form-label">Grade</label>
                        <select id="grade_id" name="grade_id" class="form-select @error('grade_id') is-invalid @enderror" required>
                            <option value="">-- Pilih grade --</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" data-branch="{{ $grade->branch_id }}" data-room="{{ $grade->category?->subject?->room_id }}"
                                    data-duration="{{ $grade->duration_minutes }}" data-teachers="{{ $grade->teachers->pluck('id')->join(',') }}"
                                    @selected(old('grade_id', $item->grade_id) === $grade->id)>
                                    {{ $grade->label() }}{{ $grades->pluck('branch_id')->unique()->count() > 1 ? ' — '.$grade->branch?->name : '' }}{{ $inactive($grade) }}
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
                                <option value="{{ $teacher->id }}" data-branch="{{ $teacher->branch_id }}" @selected(old('teacher_id', $item->teacher_id) === $teacher->id)>{{ $teacher->name }}{{ $inactive($teacher) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" data-teacher-hint>Hanya pengajar yang ditandai mengajar grade ini.</div>
                        @error('teacher_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="room_id" class="form-label">Ruangan</label>
                        <select id="room_id" name="room_id" class="form-select @error('room_id') is-invalid @enderror" required>
                            <option value="">-- Pilih ruangan --</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" data-branch="{{ $room->branch_id }}" @selected(old('room_id', $item->room_id) === $room->id)>{{ $room->name }}{{ $inactive($room) }}</option>
                            @endforeach
                        </select>
                        @error('room_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="day_of_week" class="form-label">Hari</label>
                        <select id="day_of_week" name="day_of_week" class="form-select @error('day_of_week') is-invalid @enderror" required>
                            @foreach (\App\Models\ScheduleRoutine::DAYS as $value => $label)
                                <option value="{{ $value }}" @selected((int) old('day_of_week', $item->day_of_week ?? 1) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('day_of_week') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-2">
                        <label for="start_time" class="form-label">Jam Mulai</label>
                        <input type="time" id="start_time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time', $item->start_time ? substr($item->start_time, 0, 5) : '') }}" required>
                        @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="duration_minutes" class="form-label">Durasi (menit)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" min="15" max="600" step="5" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ old('duration_minutes', $item->duration_minutes) }}" required>
                        @error('duration_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="capacity" class="form-label">Kapasitas Murid</label>
                        <input type="number" id="capacity" name="capacity" min="1" max="100" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $item->capacity) }}" required>
                        <div class="form-text">1 = privat. Lebih dari 1 = kelas grup.</div>
                        @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="starts_on" class="form-label">Mulai Berlaku</label>
                        <input type="date" id="starts_on" name="starts_on" class="form-control @error('starts_on') is-invalid @enderror" value="{{ old('starts_on', $item->starts_on?->format('Y-m-d')) }}" required>
                        @error('starts_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="ends_on" class="form-label">Berlaku Sampai (opsional)</label>
                        <input type="date" id="ends_on" name="ends_on" class="form-control @error('ends_on') is-invalid @enderror" value="{{ old('ends_on', $item->ends_on?->format('Y-m-d')) }}">
                        <div class="form-text">Kosongkan kalau berlaku terus.</div>
                        @error('ends_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <label class="form-label mb-0">Murid <span class="text-muted small" data-student-count></span></label>
                            <input type="search" class="form-control form-control-sm w-auto" placeholder="Cari murid" data-student-search>
                        </div>
                        @error('student_ids') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                        @error('student_ids.*') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                        <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto">
                            <div class="row g-1">
                                @forelse ($students as $student)
                                    <div class="col-md-6 col-xl-4" data-student data-branch="{{ $student->branch_id }}" data-name="{{ strtolower($student->name) }}">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="student_ids[]" value="{{ $student->id }}" id="student-{{ $student->id }}" @checked($selectedStudents->contains($student->id))>
                                            <label class="form-check-label" for="student-{{ $student->id }}">{{ $student->name }}{{ $inactive($student) }}</label>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted small">Belum ada murid. Tambahkan di tab Murid dulu.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            @foreach (\App\Models\ScheduleRoutine::STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Tidak Aktif = sesi mendatang yang belum diabsen dihapus, riwayat tetap ada.</div>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Catatan (opsional)</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="form-control">{{ old('notes', $item->notes) }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route($routes.'.routines.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('routine-form');
            const grade = form.querySelector('#grade_id');
            const teacher = form.querySelector('#teacher_id');
            const room = form.querySelector('#room_id');
            const capacity = form.querySelector('#capacity');
            const count = form.querySelector('[data-student-count]');
            const search = form.querySelector('[data-student-search]');
            const students = [...form.querySelectorAll('[data-student]')];
            const editing = {{ $item->exists ? 'true' : 'false' }};

            const filterOptions = (select, keep) => [...select.options].forEach(option => {
                if (!option.value) return;
                option.hidden = !keep(option);
                if (option.hidden && option.selected) select.value = '';
            });

            const applyGrade = (initial) => {
                const selected = grade.selectedOptions[0];
                const branch = selected?.dataset.branch;
                const teachers = (selected?.dataset.teachers || '').split(',').filter(Boolean);

                filterOptions(teacher, option => !branch || teachers.includes(option.value));
                filterOptions(room, option => !branch || option.dataset.branch === branch);
                students.forEach(box => {
                    const show = !branch || box.dataset.branch === branch;
                    box.classList.toggle('d-none', !show);
                    if (!show) box.querySelector('input').checked = false;
                });

                if (selected?.value && !(initial && editing)) {
                    if (selected.dataset.room && !room.value) room.value = selected.dataset.room;
                    if (selected.dataset.duration) form.querySelector('#duration_minutes').value = selected.dataset.duration;
                }
                updateCount();
            };

            const updateCount = () => {
                const checked = form.querySelectorAll('[name="student_ids[]"]:checked').length;
                const max = Number(capacity.value) || 1;
                count.textContent = `(${checked}/${max} dipilih${checked > max ? ' — melebihi kapasitas' : ''})`;
                count.classList.toggle('text-danger', checked > max);
            };

            grade.addEventListener('change', () => applyGrade(false));
            capacity.addEventListener('input', updateCount);
            form.addEventListener('change', event => event.target.name === 'student_ids[]' && updateCount());
            search.addEventListener('input', () => students.forEach(box => {
                box.style.display = box.dataset.name.includes(search.value.toLowerCase()) ? '' : 'none';
            }));
            applyGrade(true);
        })();
    </script>
@endpush
