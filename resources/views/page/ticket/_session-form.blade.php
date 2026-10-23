{{-- Form tambah/ubah sesi. Param: $session (null = baru), $bag, $action, $method, $categories --}}
@php
    $useOld = old('_form') === $bag;
    $val = fn ($key, $default = null) => $useOld ? old($key, $default) : $default;
    $fmt = fn ($date) => $date?->format('Y-m-d\TH:i');
    $errs = $errors->getBag($bag);
    $chosen = $val('category_ids', $session?->category_ids ?? []) ?? [];
@endphp

<form method="POST" action="{{ $action }}" data-session-form>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <input type="hidden" name="_form" value="{{ $bag }}">

    @if ($errs->any())
        <div class="alert alert-danger small py-2">{{ $errs->first() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label fw-semibold">Nama sesi / kelas</label>
            <input type="text" name="name" maxlength="150" required class="form-control @if ($errs->has('name')) is-invalid @endif" value="{{ $val('name', $session?->name) }}" placeholder="mis. Kelas Digital Marketing">
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold">Ruangan <span class="text-muted fw-normal">(opsional)</span></label>
            <input type="text" name="room" maxlength="100" class="form-control" value="{{ $val('room', $session?->room) }}" placeholder="mis. Ruang Melati 2">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Mulai</label>
            <input type="datetime-local" name="starts_at" required class="form-control @if ($errs->has('starts_at')) is-invalid @endif" value="{{ $val('starts_at', $fmt($session?->starts_at)) }}" data-starts>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Selesai</label>
            <input type="datetime-local" name="ends_at" required class="form-control @if ($errs->has('ends_at')) is-invalid @endif" value="{{ $val('ends_at', $fmt($session?->ends_at)) }}" data-ends>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Absensi dibuka</label>
            <div class="input-group">
                <input type="number" name="open_before_minutes" min="0" max="720" required class="form-control" value="{{ $val('open_before_minutes', $session?->open_before_minutes ?? 30) }}" data-open>
                <span class="input-group-text">menit sebelum mulai</span>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Batas terlambat</label>
            <div class="input-group">
                <input type="number" name="late_minutes" min="0" max="1440" class="form-control" value="{{ $val('late_minutes', $session?->late_minutes) }}" placeholder="kosong" data-late>
                <span class="input-group-text">menit setelah mulai</span>
            </div>
            <div class="form-text">Kosongkan kalau peserta boleh absen sampai kelas selesai.</div>
        </div>
        <div class="col-12">
            <div class="rounded-3 bg-light px-3 py-2 small" data-window><i class="ri-time-line me-1"></i>Isi jam mulai dan selesai dulu ya.</div>
        </div>
        @if ($categories->isNotEmpty())
            <div class="col-12">
                <label class="form-label fw-semibold mb-1">Siapa yang boleh ikut?</label>
                <div class="form-text mt-0 mb-2">Tidak dicentang sama sekali = semua kategori tiket boleh ikut sesi ini.</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <input type="checkbox" class="btn-check" name="category_ids[]" value="{{ $category->id }}" id="{{ $bag }}-cat-{{ $category->id }}" @checked(in_array($category->id, $chosen, true))>
                        <label class="btn btn-sm btn-outline-primary rounded-pill" for="{{ $bag }}-cat-{{ $category->id }}">{{ $category->name }}</label>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="form-check form-switch">
                <input type="hidden" name="status" value="inactive">
                <input class="form-check-input" type="checkbox" role="switch" name="status" value="active" id="{{ $bag }}-status" @checked($val('status', $session?->status ?? 'active') === 'active')>
                <label class="form-check-label" for="{{ $bag }}-status">Sesi aktif</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>{{ $session ? 'Simpan Perubahan' : 'Tambah Sesi' }}</button>
        </div>
    </div>
</form>

@once
    @push('scripts')
        <script>
            // Ringkasan jendela absensi, supaya admin langsung paham kapan petugas bisa scan.
            document.querySelectorAll('[data-session-form]').forEach(function (form) {
                var box = form.querySelector('[data-window]');
                // Nilai input datetime-local dibaca apa adanya (jam acara), jadi dipakai tanpa konversi zona waktu.
                function hm(d) { return String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0'); }
                function refresh() {
                    var s = new Date(form.querySelector('[data-starts]').value), e = new Date(form.querySelector('[data-ends]').value);
                    if (isNaN(s) || isNaN(e)) { box.innerHTML = '<i class="ri-time-line me-1"></i>Isi jam mulai dan selesai dulu ya.'; box.className = 'rounded-3 bg-light px-3 py-2 small'; return; }
                    if (e <= s) { box.innerHTML = '<i class="ri-error-warning-line me-1"></i>Jam selesai harus setelah jam mulai.'; box.className = 'rounded-3 bg-danger-subtle text-danger px-3 py-2 small'; return; }
                    var open = new Date(s.getTime() - (parseInt(form.querySelector('[data-open]').value, 10) || 0) * 60000);
                    var lateVal = form.querySelector('[data-late]').value, close = e;
                    if (lateVal !== '') { var late = new Date(s.getTime() + parseInt(lateVal, 10) * 60000); if (late < e) close = late; }
                    box.className = 'rounded-3 bg-success-subtle text-success px-3 py-2 small';
                    box.innerHTML = '<i class="ri-checkbox-circle-line me-1"></i>Petugas bisa scan absensi pukul <b>' + hm(open) + ' – ' + hm(close) + '</b>. Di luar jam itu, kartu otomatis ditolak.';
                }
                form.addEventListener('input', refresh);
                refresh();
            });
        </script>
    @endpush
@endonce
