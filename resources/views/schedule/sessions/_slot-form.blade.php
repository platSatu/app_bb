@php($openings = $openings ?? null)
<form method="POST" action="{{ $action }}" class="row g-2" @if ($openings) id="slot-pick-form" @endif>
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    @if ($openings)
        {{-- Jam dipilih, bukan diketik. Disusun ke bawah per hari supaya lega di kartu yang sempit:
             tiap baris satu hari, isinya jam yang benar-benar kosong untuk pengajar, ruangan,
             dan murid pertemuan ini. Jam yang terpakai atau sudah lewat tidak ditawarkan. --}}
        <div class="col-12">
            <div class="list-group">
                @foreach ($openings['days'] as $day)
                    @php($cells = collect($day['cells']))
                    @php($current = $cells->firstWhere('current', true))
                    @php($free = $cells->filter(fn ($cell) => ! $cell['taken'] && ! $cell['past'] && ! $cell['current'])->values())
                    <div class="list-group-item d-flex flex-wrap align-items-center gap-2 py-2">
                        <div class="flex-shrink-0" style="min-width: 7.5rem">
                            <span class="fw-semibold">{{ $day['date']->translatedFormat('l') }}</span>
                            <span class="d-block text-muted" style="font-size: .75rem">{{ $day['date']->translatedFormat('d M Y') }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 flex-grow-1">
                            @if ($current)
                                <span class="d-inline-flex align-items-center gap-1 border border-primary bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size: .78rem">
                                    <i class="ri-map-pin-time-line"></i>{{ str_replace(':', '.', $current['time']) }} &middot; sekarang
                                </span>
                            @endif
                            @forelse ($free as $cell)
                                <label class="d-inline-flex align-items-center gap-1 border rounded-pill px-2 py-1 bg-white mb-0" style="font-size: .78rem; cursor: pointer">
                                    <input type="radio" name="pilih_jam" class="form-check-input m-0 flex-shrink-0"
                                        data-pick-date="{{ $cell['date'] }}" data-pick-time="{{ $cell['time'] }}"
                                        data-pick-label="{{ $day['date']->translatedFormat('l, d M') }} jam {{ str_replace(':', '.', $cell['time']) }}">
                                    {{ str_replace(':', '.', $cell['time']) }}
                                </label>
                            @empty
                                @unless ($current)
                                    <span class="text-muted" style="font-size: .78rem">Tidak ada jam kosong</span>
                                @endunless
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text" data-pick-note>Pilih salah satu jam untuk memindahkan pertemuan ini.</div>
        </div>
        <input type="hidden" name="date" value="{{ old('date', $default?->date->toDateString()) }}" data-pick-field="date">
        <input type="hidden" name="time" value="{{ old('time', $default?->start_at->format('H:i')) }}" data-pick-field="time">
    @else
        <div class="col-6">
            <input type="date" name="date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date', $default?->date->toDateString()) }}" required>
        </div>
        <div class="col-6">
            <input type="time" name="time" class="form-control" value="{{ old('time', $default?->start_at->format('H:i')) }}" required>
        </div>
    @endif

    <div class="col-6">
        <select name="teacher_id" class="form-select" required>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $default?->teacher_id ?? $session->teacher_id) === $teacher->id)>{{ $teacher->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6">
        <select name="room_id" class="form-select" required>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected(old('room_id', $default?->room_id ?? $session->room_id) === $room->id)>{{ $room->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <button class="btn btn-outline-primary" @if ($openings) data-pick-submit disabled @endif>{{ $button }}</button>
    </div>
</form>

@if ($openings)
    @push('scripts')
        <script>
            // Centang jam -> isi tanggal & jam tersembunyi, lalu tombol simpan dibuka.
            (() => {
                const form = document.getElementById('slot-pick-form');
                if (!form) return;

                const note = form.querySelector('[data-pick-note]');
                const submit = form.querySelector('[data-pick-submit]');
                const dateField = form.querySelector('[data-pick-field="date"]');
                const timeField = form.querySelector('[data-pick-field="time"]');

                form.querySelectorAll('input[name="pilih_jam"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        if (!radio.checked) return;
                        dateField.value = radio.dataset.pickDate;
                        timeField.value = radio.dataset.pickTime;
                        note.textContent = 'Dipindah ke ' + radio.dataset.pickLabel + '. Tekan {{ $button }} untuk menyimpan.';
                        submit.disabled = false;
                    });
                });
            })();
        </script>
    @endpush
@endif
