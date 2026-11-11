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
