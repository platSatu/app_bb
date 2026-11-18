@php($openings = $openings ?? null)
<form method="POST" action="{{ $action }}" class="row g-2" @if ($openings) id="slot-pick-form" @endif>
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    @if ($openings)
        {{-- Jam dipilih dari papan, bukan diketik: yang bisa dicentang hanya jam yang benar-benar
             kosong untuk pengajar, ruangan, dan murid pertemuan ini. --}}
        <div class="col-12">
            <div class="table-responsive border rounded">
                <table class="table table-sm table-bordered align-middle mb-0 text-center" style="table-layout: fixed">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5.5rem" class="small text-start">Jam</th>
                            @foreach ($openings['days'] as $day)
                                <th class="small">
                                    {{ $day['label'] }}
                                    <span class="d-block fw-normal text-muted" style="font-size: .7rem">{{ $day['date']->translatedFormat('d M') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($openings['times'] as $time)
                            <tr>
                                <td class="small fw-semibold text-nowrap text-start">{{ str_replace(':', '.', $time) }}</td>
                                @foreach ($openings['days'] as $day)
                                    @php($cell = $day['cells'][$time])
                                    <td class="p-1 {{ $cell['current'] ? 'bg-primary-subtle' : '' }}">
                                        @if ($cell['current'])
                                            <span class="text-primary fw-semibold" style="font-size: .72rem">sekarang</span>
                                        @elseif ($cell['taken'] || $cell['past'])
                                            <span class="text-muted" style="font-size: .72rem">{{ $cell['taken'] ? 'terpakai' : 'lewat' }}</span>
                                        @else
                                            <label class="d-flex align-items-center justify-content-center gap-1 text-muted mb-0" style="font-size: .72rem; cursor: pointer">
                                                <input type="radio" name="pilih_jam" class="form-check-input m-0 flex-shrink-0"
                                                    data-pick-date="{{ $cell['date'] }}" data-pick-time="{{ $cell['time'] }}"
                                                    data-pick-label="{{ $day['date']->translatedFormat('l, d M') }} jam {{ str_replace(':', '.', $cell['time']) }}">
                                                bisa
                                            </label>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" class="small text-muted py-3">Belum ada jam buka di ruangan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="form-text" data-pick-note>Centang salah satu jam yang masih bisa dipakai.</div>
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
