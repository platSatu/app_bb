@include('schedule._contact-fields')
<div class="col-12">
    <label class="form-label">Grade yang Diajar</label>
    @error('grade_ids.*') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
    @forelse ($grades as $branchId => $branchGrades)
        <div class="border rounded p-2 mb-2" data-branch-grades="{{ $branchId }}">
            @if ($grades->count() > 1)
                <div class="small text-muted mb-1">{{ $branchGrades->first()->branch?->name }}</div>
            @endif
            <div class="row g-1">
                @foreach ($branchGrades as $grade)
                    <div class="col-md-6 col-xl-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="grade_ids[]" value="{{ $grade->id }}" id="grade-{{ $grade->id }}" @checked($selectedGrades->contains($grade->id))>
                            <label class="form-check-label" for="grade-{{ $grade->id }}">{{ $grade->label() }}{{ $grade->isActive() ? '' : ' (tidak aktif)' }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-muted small">Belum ada grade yang bisa dipilih. Grade bisa ditambahkan nanti lewat Edit pengajar.</div>
    @endforelse
</div>

@push('scripts')
    <script>
        (() => {
            const select = document.querySelector('[data-branch-select]');
            if (!select) return;
            const sync = () => document.querySelectorAll('[data-branch-grades]').forEach(box => {
                const show = box.dataset.branchGrades === select.value;
                box.classList.toggle('d-none', !show);
                if (!show) box.querySelectorAll('input').forEach(input => input.checked = false);
            });
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
