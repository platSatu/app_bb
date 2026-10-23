{{-- Pilih banyak customer Invoice di branch. $available, $action, $button --}}
<form method="POST" action="{{ $action }}" data-picker>
    @csrf
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <input type="search" class="form-control form-control-sm w-auto flex-grow-1" placeholder="Cari nama, WA, atau email" data-picker-search>
        <button type="button" class="btn btn-sm btn-light" data-picker-all>Pilih yang tampil</button>
    </div>
    @error('customer_ids') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
    <div class="border rounded p-2 mb-2" style="max-height: 280px; overflow-y: auto">
        @forelse ($available as $customer)
            <div class="form-check" data-picker-row data-text="{{ strtolower($customer->name.' '.$customer->phone.' '.$customer->email) }}">
                <input class="form-check-input" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" id="pick-{{ $customer->id }}">
                <label class="form-check-label" for="pick-{{ $customer->id }}">
                    {{ $customer->displayName() }}
                    <span class="text-muted small">{{ $customer->phone ? '+'.$customer->phone : '' }} {{ $customer->email }}</span>
                </label>
            </div>
        @empty
            <div class="text-muted small">Semua customer Invoice di branch ini sudah masuk. Tambahkan customer baru dengan form di sebelah.</div>
        @endforelse
    </div>
    @if ($available->isNotEmpty())
        <button type="submit" class="btn btn-primary btn-sm">{{ $button }} <span data-picker-count></span></button>
    @endif
</form>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-picker]').forEach(function (form) {
                var rows = [].slice.call(form.querySelectorAll('[data-picker-row]'));
                var count = form.querySelector('[data-picker-count]');
                var update = function () { if (count) { var n = form.querySelectorAll('[name="customer_ids[]"]:checked').length; count.textContent = n ? '(' + n + ')' : ''; } };
                form.querySelector('[data-picker-search]').addEventListener('input', function (e) {
                    var q = e.target.value.toLowerCase();
                    rows.forEach(function (row) { row.style.display = row.dataset.text.indexOf(q) > -1 ? '' : 'none'; });
                });
                form.querySelector('[data-picker-all]').addEventListener('click', function () {
                    rows.forEach(function (row) { if (row.style.display !== 'none') row.querySelector('input').checked = true; });
                    update();
                });
                form.addEventListener('change', update);
            });
        </script>
    @endpush
@endonce
