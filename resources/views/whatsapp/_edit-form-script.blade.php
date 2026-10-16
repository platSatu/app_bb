{{-- Form samping dipakai untuk tambah & ubah: tombol [data-edit] mengisi form dengan [data-values]. --}}
<script>
    (function () {
        var form = document.querySelector('[data-form]');
        var title = document.querySelector('[data-form-title]');
        var cancel = form.querySelector('[data-cancel]');
        var reset = function () {
            form.action = form.dataset.store;
            form.elements.namedItem('_method').value = 'POST';
            title.textContent = @json($createTitle);
            cancel.classList.add('d-none');
            form.reset();
        };
        document.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var values = JSON.parse(btn.dataset.values);
                form.action = btn.dataset.edit;
                form.elements.namedItem('_method').value = 'PUT';
                title.textContent = @json($editTitle);
                Object.keys(values).forEach(function (key) {
                    var input = form.elements.namedItem(key);
                    if (!input) return;
                    if (input.type === 'checkbox') input.checked = !!values[key];
                    else input.value = values[key] === null || values[key] === undefined ? '' : values[key];
                });
                cancel.classList.remove('d-none');
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        cancel.addEventListener('click', reset);
    })();
</script>
