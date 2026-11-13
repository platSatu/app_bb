{{-- Hapus banyak customer: bar merah muncul saat ada baris dicentang, bisa "pilih semua yang cocok dengan filter" (all=1).
     Dipakai Customers superadmin & Phone Book WhatsApp. Baris tabel: <input name="ids[]" form="bulk-delete-form" data-bulk-row>,
     header: <input data-bulk-page>. $action (URL DELETE + filter aktif), $customers (paginator), $noun, $confirm (:count = jumlah). --}}
<form id="bulk-delete-form" method="POST" action="{{ $action }}"
    class="alert alert-danger d-none align-items-center flex-wrap gap-2 py-2 mb-2" data-bulk-bar data-total="{{ $customers->total() }}">
    @csrf
    @method('DELETE')
    <input type="hidden" name="all" value="0" data-bulk-all>
    <span class="small"><b data-bulk-count>0</b> {{ $noun }} dipilih.</span>
    @if ($customers->total() > $customers->count())
        <button type="button" class="btn btn-link btn-sm p-0 d-none" data-bulk-select-all>Pilih semua {{ number_format($customers->total(), 0, ',', '.') }} {{ $noun }} yang cocok dengan filter</button>
    @endif
    <button type="submit" class="btn btn-danger btn-sm ms-auto"><i class="ri-delete-bin-line"></i> Hapus yang dipilih</button>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var bar = document.querySelector('[data-bulk-bar]'), page = document.querySelector('[data-bulk-page]');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-row]'));
        var all = bar.querySelector('[data-bulk-all]'), selectAll = bar.querySelector('[data-bulk-select-all]');
        var total = Number(bar.dataset.total);
        var number = function (n) { return n.toLocaleString('id-ID'); };

        var refresh = function () {
            var checked = rows.filter(function (r) { return r.checked; }).length;
            if (all.value !== '1') bar.querySelector('[data-bulk-count]').textContent = number(checked);
            bar.classList.toggle('d-none', checked === 0);
            bar.classList.toggle('d-flex', checked > 0);
            page.checked = checked === rows.length;
            page.indeterminate = checked > 0 && checked < rows.length;
            if (selectAll) selectAll.classList.toggle('d-none', checked !== rows.length || all.value === '1');
        };
        var reset = function () { all.value = '0'; };

        page.addEventListener('change', function () { reset(); rows.forEach(function (r) { r.checked = page.checked; }); refresh(); });
        rows.forEach(function (r) { r.addEventListener('change', function () { reset(); refresh(); }); });
        if (selectAll) selectAll.addEventListener('click', function () {
            all.value = '1';
            bar.querySelector('[data-bulk-count]').textContent = number(total);
            selectAll.classList.add('d-none');
        });
        bar.addEventListener('submit', function (event) {
            var count = all.value === '1' ? total : rows.filter(function (r) { return r.checked; }).length;
            if (!confirm({{ \Illuminate\Support\Js::from($confirm) }}.replace(':count', number(count)))) {
                event.preventDefault();
                return;
            }
            bar.querySelector('[type=submit]').disabled = true;
        });
    });
</script>
