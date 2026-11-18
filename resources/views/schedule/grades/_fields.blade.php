{{-- Bulanan = harga per bulan (jadwal per bulan). Paket = harga total untuk sejumlah pertemuan (privat, pola bebas). --}}
<div class="col-12">
    <label class="form-label d-block">Jenis Harga</label>
    <div class="d-flex flex-wrap gap-2">
        @foreach (\App\Models\ScheduleGrade::BILLING_LABELS as $value => $label)
            <input type="radio" class="btn-check" name="billing" value="{{ $value }}" id="billing-{{ $value }}" data-grade="billing"
                @checked(old('billing', $item->billing ?? \App\Models\ScheduleGrade::BILLING_MONTHLY) === $value)>
            <label class="btn btn-outline-primary rounded-pill px-3" for="billing-{{ $value }}">{{ $label }}</label>
        @endforeach
    </div>
    <div class="form-text" data-grade="billing-hint"></div>
    @error('billing') <div class="text-danger small">{{ $message }}</div> @enderror
</div>
<div class="col-md-6">
    <label for="price" class="form-label" data-grade="price-label">Harga per Bulan</label>
    <div class="input-group">
        <span class="input-group-text">Rp</span>
        <input type="number" id="price" name="price" min="0" step="1" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $item->price ?? 0) }}" required data-grade="price">
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="col-md-3">
    <label for="meetings_per_month" class="form-label" data-grade="meetings-label">Pertemuan / Bulan</label>
    <input type="number" id="meetings_per_month" name="meetings_per_month" min="1" max="200" class="form-control @error('meetings_per_month') is-invalid @enderror" value="{{ old('meetings_per_month', $item->meetings_per_month ?? 4) }}" required data-grade="meetings">
    @error('meetings_per_month') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="col-md-3">
    <label for="duration_minutes" class="form-label">Durasi (menit)</label>
    <input type="number" id="duration_minutes" name="duration_minutes" min="15" max="600" step="5" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ old('duration_minutes', $item->duration_minutes ?? 60) }}" required>
    @error('duration_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="col-md-6">
    <label for="teacher_percent" class="form-label">Bagian Pengajar (%)</label>
    <div class="input-group">
        <input type="number" id="teacher_percent" name="teacher_percent" min="0" max="100" step="0.01" class="form-control @error('teacher_percent') is-invalid @enderror" value="{{ old('teacher_percent', $item->teacher_percent ?? 0) }}" required data-grade="percent">
        <span class="input-group-text">%</span>
        @error('teacher_percent') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="form-text">Sisanya otomatis menjadi bagian owner. Honor dihitung per murid per pertemuan.</div>
</div>
<div class="col-md-6">
    <label class="form-label">Rincian per Pertemuan</label>
    <div class="bg-light rounded p-2 small" data-grade="summary"></div>
</div>

{{-- Pengajar ditugaskan dari sini (sebelumnya dari form Pengajar). Tabel penghubungnya sama,
     jadi data lama tetap terbaca dan tidak perlu dipindahkan. --}}
<div class="col-12" id="pengajar">
    <label class="form-label">Pengajar yang Mengajar Grade Ini</label>
    @error('teacher_ids.*') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
    {{-- $teachers datang sebagai daftar datar (dipakai juga oleh dropdown di baris Jam Buka),
         jadi pengelompokan per branch dikerjakan di sini saja. --}}
    @php($byBranch = $teachers->groupBy('branch_id'))
    @forelse ($byBranch as $branchId => $branchTeachers)
        <div class="border rounded p-2 mb-2" data-branch-teachers="{{ $branchId }}">
            @if ($byBranch->count() > 1)
                <div class="small text-muted mb-1">{{ $branchTeachers->first()->branch?->name }}</div>
            @endif
            <div class="row g-1">
                @foreach ($branchTeachers as $teacher)
                    <div class="col-md-6 col-xl-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" id="teacher-{{ $teacher->id }}" @checked($selectedTeachers->contains($teacher->id))>
                            <label class="form-check-label" for="teacher-{{ $teacher->id }}">{{ $teacher->name }}{{ $teacher->isActive() ? '' : ' (tidak aktif)' }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-muted small">Belum ada pengajar yang bisa dipilih. Tambahkan dulu di tab Pengajar, lalu tugaskan di sini.</div>
    @endforelse
    <div class="form-text">Centang dulu pengajarnya, baru namanya bisa dipakai di jam buka di bawah.</div>
</div>

{{-- Jam buka: tiap baris dipecah sendiri mengikuti Durasi di atas. Sekali simpan, grade jadi,
     pengajarnya ditugaskan, dan slotnya langsung terbentuk -- muridnya diisi lewat tombol Isi Murid. --}}
<div class="col-12" data-slots-block>
    <label class="form-label mb-1">Jam Buka</label>
    <div class="form-text mt-0 mb-2">Tulis jendela jamnya, misalnya Senin 13.00–15.00. Sistem memecahnya sendiri sesuai durasi grade.</div>

    @if (($slotCount ?? 0) > 0)
        <div class="alert alert-light border small py-2">Grade ini sudah punya <strong>{{ $slotCount }} slot</strong>. Baris di bawah hanya <em>menambah</em> slot baru; yang sudah ada diatur lewat tombol Isi Murid di tabel Grade.</div>
    @endif

    @error('slots') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

    <div class="row g-2 mb-2">
        <div class="col-md-3">
            <label for="slot_month" class="form-label small mb-1">Bulan</label>
            <input type="month" id="slot_month" name="slot_month" class="form-control @error('slot_month') is-invalid @enderror" value="{{ old('slot_month', now()->format('Y-m')) }}">
            @error('slot_month') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="slot_capacity" class="form-label small mb-1">Murid per Slot</label>
            <input type="number" id="slot_capacity" name="slot_capacity" min="1" max="100" class="form-control @error('slot_capacity') is-invalid @enderror" value="{{ old('slot_capacity', 1) }}">
            <div class="form-text">1 = privat.</div>
            @error('slot_capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div data-slot-rows>
        @foreach (old('slots', [['day' => 1, 'from' => '', 'until' => '', 'teacher' => '', 'room' => '']]) as $i => $row)
            <div class="row g-2 align-items-end mb-2" data-slot-row>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Hari</label>
                    <select name="slots[{{ $i }}][day]" class="form-select form-select-sm">
                        @foreach (\App\Models\ScheduleRoutine::DAYS as $value => $label)
                            <option value="{{ $value }}" @selected((int) ($row['day'] ?? 1) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3 col-md-2">
                    <label class="form-label small mb-1">Mulai</label>
                    <input type="time" name="slots[{{ $i }}][from]" class="form-control form-control-sm @error('slots.'.$i.'.from') is-invalid @enderror" value="{{ $row['from'] ?? '' }}" data-slot-from>
                </div>
                <div class="col-3 col-md-2">
                    <label class="form-label small mb-1">Selesai</label>
                    <input type="time" name="slots[{{ $i }}][until]" class="form-control form-control-sm @error('slots.'.$i.'.until') is-invalid @enderror" value="{{ $row['until'] ?? '' }}" data-slot-until>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Pengajar</label>
                    <select name="slots[{{ $i }}][teacher]" class="form-select form-select-sm @error('slots.'.$i.'.teacher') is-invalid @enderror" data-slot-teacher>
                        <option value="">--</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" data-branch="{{ $teacher->branch_id }}" @selected(($row['teacher'] ?? '') === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Ruangan</label>
                    <select name="slots[{{ $i }}][room]" class="form-select form-select-sm @error('slots.'.$i.'.room') is-invalid @enderror">
                        <option value="">--</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" data-branch="{{ $room->branch_id }}" @selected(($row['room'] ?? '') === $room->id)>{{ $room->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <span class="badge bg-light text-body border flex-grow-1 text-center py-2" data-slot-count>—</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-slot-remove title="Hapus baris"><i class="ri-close-line"></i></button>
                </div>
                @error('slots.'.$i.'.from') <div class="col-12"><div class="text-danger small">{{ $message }}</div></div> @enderror
                @error('slots.'.$i.'.until') <div class="col-12"><div class="text-danger small">{{ $message }}</div></div> @enderror
                @error('slots.'.$i.'.teacher') <div class="col-12"><div class="text-danger small">{{ $message }}</div></div> @enderror
                @error('slots.'.$i.'.room') <div class="col-12"><div class="text-danger small">{{ $message }}</div></div> @enderror
                @error('slots.'.$i.'.day') <div class="col-12"><div class="text-danger small">{{ $message }}</div></div> @enderror
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary" data-slot-add><i class="ri-add-line me-1"></i>Tambah Baris Jam</button>
</div>

@push('scripts')
    <script>
        // Baris jam: tambah/hapus baris, dan hitung jumlah slot tiap baris sesuai Durasi di atas.
        (() => {
            const block = document.querySelector('[data-slots-block]');
            if (!block) return;

            const rows = block.querySelector('[data-slot-rows]');
            const duration = document.getElementById('duration_minutes');
            const MAX_ROWS = 12;

            const toMinutes = (value) => {
                const [h, m] = (value || '').split(':').map(Number);
                return Number.isFinite(h) && Number.isFinite(m) ? h * 60 + m : null;
            };

            const countRow = (row) => {
                const minutes = Number(duration?.value) || 0;
                const from = toMinutes(row.querySelector('[data-slot-from]').value);
                const until = toMinutes(row.querySelector('[data-slot-until]').value);
                const badge = row.querySelector('[data-slot-count]');
                let slots = 0;

                if (minutes > 0 && from !== null && until !== null) {
                    while (slots < 24 && from + minutes * (slots + 1) <= until) slots++;
                }

                badge.textContent = slots ? slots + ' slot' : '—';
                badge.classList.toggle('bg-success-subtle', slots > 0);
                badge.classList.toggle('text-success', slots > 0);
                badge.classList.toggle('bg-light', slots === 0);
            };

            const countAll = () => rows.querySelectorAll('[data-slot-row]').forEach(countRow);

            const renumber = () => [...rows.querySelectorAll('[data-slot-row]')].forEach((row, i) => {
                row.querySelectorAll('[name^="slots["]').forEach(input => {
                    input.name = input.name.replace(/slots\[\d+\]/, `slots[${i}]`);
                });
            });

            block.querySelector('[data-slot-add]').addEventListener('click', () => {
                const all = rows.querySelectorAll('[data-slot-row]');
                if (all.length >= MAX_ROWS) return;
                const copy = all[all.length - 1].cloneNode(true);
                copy.querySelectorAll('input').forEach(input => input.value = '');
                copy.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                copy.querySelectorAll('.text-danger').forEach(el => el.remove());
                rows.appendChild(copy);
                renumber();
                countRow(copy);
            });

            // Baris terakhir tidak dihapus, hanya dikosongkan -- supaya tidak ada tombol tambah tanpa baris.
            rows.addEventListener('click', (event) => {
                const button = event.target.closest('[data-slot-remove]');
                if (!button) return;
                const row = button.closest('[data-slot-row]');
                if (rows.querySelectorAll('[data-slot-row]').length > 1) row.remove();
                else row.querySelectorAll('input, select').forEach(el => el.value = el.tagName === 'SELECT' ? el.options[0].value : '');
                renumber();
                countAll();
            });

            rows.addEventListener('input', countAll);
            duration?.addEventListener('input', countAll);
            countAll();
        })();
    </script>
@endpush

@push('scripts')
    <script>
        // Superadmin ganti branch: hanya pengajar branch itu yang tampil, centang branch lain dilepas
        // supaya tidak ikut tersimpan. Pola sama seperti pemilih grade di form Pengajar.
        (() => {
            const select = document.querySelector('[data-branch-select]');
            if (!select) return;
            const sync = () => document.querySelectorAll('[data-branch-teachers]').forEach(box => {
                const show = box.dataset.branchTeachers === select.value;
                box.classList.toggle('d-none', !show);
                if (!show) box.querySelectorAll('input').forEach(input => input.checked = false);
            });
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush

@push('scripts')
    <script>
        (() => {
            const get = key => Number(document.querySelector(`[data-grade=${key}]`).value) || 0;
            const out = document.querySelector('[data-grade=summary]');
            const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
            const isPackage = () => document.querySelector('[data-grade=billing]:checked')?.value === 'package';
            const update = () => {
                const pkg = isPackage();
                document.querySelector('[data-grade=price-label]').textContent = pkg ? 'Harga Paket (total)' : 'Harga per Bulan';
                document.querySelector('[data-grade=meetings-label]').textContent = pkg ? 'Jumlah Pertemuan' : 'Pertemuan / Bulan';
                document.querySelector('[data-grade=billing-hint]').textContent = pkg
                    ? 'Paket: harga penuh untuk sejumlah pertemuan, mis. 15x Rp 3.000.000. Kelas privat, pola pertemuan diatur saat membuat jadwal.'
                    : 'Bulanan: harga per bulan, jadwal dibuat per bulan lalu disalin ke bulan berikutnya.';
                const meetings = get('meetings');
                const perMeeting = meetings > 0 ? Math.round(get('price') / meetings) : 0;
                const percent = Math.min(100, Math.max(0, get('percent')));
                const teacher = Math.round(perMeeting * percent / 100);
                out.innerHTML = `Per pertemuan: <strong>${rp(perMeeting)}</strong><br>`
                    + `Pengajar ${percent}%: ${rp(teacher)} · Owner ${+(100 - percent).toFixed(2)}%: ${rp(perMeeting - teacher)}<br>`
                    + `<span class="text-muted">${pkg ? `Paket selesai setelah pertemuan ke-${meetings}.` : `Pertemuan ke-${meetings + 1} dalam sebulan tidak dibuat.`}</span>`;
            };
            document.querySelectorAll('[data-grade]').forEach(el => el.addEventListener(el.type === 'radio' ? 'change' : 'input', update));
            update();
        })();
    </script>
@endpush
