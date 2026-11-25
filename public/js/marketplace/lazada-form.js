/*
 * Form upload ke Lazada: atribut kategori dinamis (GetCategoryAttributes).
 * Atribut produk -> attributes[name], atribut SKU (attribute_type "sku") -> sku_attributes[name].
 * Butuh public/js/marketplace/category-picker.js.
 */
(function () {
    const form = document.getElementById('marketplace-publish');
    if (!form) return;

    const { esc, getJson, categoryPicker, lockOnSubmit } = window.MarketplaceForm;
    const box = form.querySelector('[data-attributes]');
    const hint = form.querySelector('[data-attributes-hint]');
    const old = {
        attributes: JSON.parse(form.dataset.oldAttributes || '{}') || {},
        sku_attributes: JSON.parse(form.dataset.oldSkuAttributes || '{}') || {},
    };

    function field(attr) {
        const group = attr.attribute_type === 'sku' ? 'sku_attributes' : 'attributes';
        const name = `${group}[${attr.name}]`;
        const value = old[group][attr.name] ?? '';
        const req = attr.is_mandatory ? 'required' : '';
        const star = attr.is_mandatory ? ' <span class="text-danger">*</span>' : '';
        let input;

        if (attr.options.length && attr.input_type === 'multiSelect') {
            const values = Array.isArray(value) ? value : String(value).split(',');
            input = `<select name="${esc(name)}[]" class="form-select form-select-sm" multiple size="4" ${req}>${attr.options.map((o) => `<option ${values.includes(o) ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
        } else if (attr.options.length) {
            input = `<select name="${esc(name)}" class="form-select form-select-sm" ${req}><option value="">Pilih...</option>${attr.options.map((o) => `<option ${o === value ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
        } else if (attr.input_type === 'richText') {
            input = `<textarea name="${esc(name)}" class="form-control form-control-sm" rows="2" ${req}>${esc(value)}</textarea>`;
        } else {
            input = `<input type="${attr.input_type === 'numeric' ? 'number' : 'text'}" step="any" name="${esc(name)}" class="form-control form-control-sm" value="${esc(value)}" ${req}>`;
        }

        return `<div class="col-12 col-md-6 ${attr.is_mandatory ? '' : 'mp-optional d-none'}"><label class="form-label small mb-1">${esc(attr.label)}${star}</label>${input}</div>`;
    }

    form.addEventListener('marketplace:category', async (e) => {
        box.innerHTML = '';
        hint.textContent = 'Memuat atribut kategori...';
        try {
            const attrs = (await getJson(form.dataset.attributesUrl.replace('__ID__', encodeURIComponent(e.detail.id)))).attributes || [];
            box.innerHTML = attrs.map(field).join('');
            const optional = attrs.filter((a) => !a.is_mandatory).length;
            hint.innerHTML = attrs.length
                ? 'Isi atribut wajib (<span class="text-danger">*</span>).' + (optional ? ` <a href="#" data-toggle-optional>Tampilkan ${optional} atribut opsional</a>` : '')
                : 'Kategori ini tidak meminta atribut tambahan.';
        } catch (err) {
            hint.innerHTML = `<span class="text-danger">${esc(err.message)}</span>`;
        }
    });

    hint.addEventListener('click', (e) => {
        if (!e.target.matches('[data-toggle-optional]')) return;
        e.preventDefault();
        box.querySelectorAll('.mp-optional').forEach((el) => el.classList.toggle('d-none'));
    });

    categoryPicker(form);
    lockOnSubmit(form, 'Mengunggah ke Lazada...');
})();
