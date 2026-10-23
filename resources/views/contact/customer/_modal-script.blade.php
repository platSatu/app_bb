{{-- Buka modal tambah/ubah customer (#customer-modal). Tombol: [data-customer-new], [data-customer-edit] + data-customer (JSON). --}}
@push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('customer-modal');
            if (!modalEl) return;
            var form = modalEl.querySelector('[data-customer-form]');
            var open = function (title, action, method, data) {
                form.action = action;
                form.elements.namedItem('_method').value = method;
                modalEl.querySelector('[data-customer-title]').textContent = title;
                ['name', 'phone', 'email'].forEach(function (key) { form.elements.namedItem(key).value = data[key] || ''; });
                form.querySelectorAll('[name="group_ids[]"]').forEach(function (c) { c.checked = (data.groups || []).indexOf(c.value) > -1; });
                // Saat mengubah, branch tidak dipindah.
                var branch = modalEl.querySelector('[data-branch-field]');
                if (branch) branch.hidden = method !== 'POST';
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };
            var add = document.querySelector('[data-customer-new]');
            if (add) add.addEventListener('click', function () { open('Tambah Customer', form.dataset.store, 'POST', {}); });
            document.querySelectorAll('[data-customer-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () { open('Ubah Customer', btn.dataset.customerEdit, 'PUT', JSON.parse(btn.dataset.customer)); });
            });
            @if ($errors->hasAny(['name', 'phone', 'email']))
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            @endif
        })();
    </script>
@endpush
