/*
 * Form upload ke Shopee: atribut kategori (get_attribute_tree) & brand per kategori (get_brand_list).
 * Isian atribut: attributes[ID][values][] (value_id), attributes[ID][custom] (isi sendiri), attributes[ID][unit].
 * input_type: 1 pilih satu | 2 pilih satu / isi sendiri | 3 isi sendiri | 4 pilih banyak | 5 pilih banyak / isi sendiri.
 * Butuh public/js/marketplace/category-picker.js.
 */
(function () {
    const form = document.getElementById('marketplace-publish');
    if (!form) return;

    const { esc, getJson, categoryPicker, lockOnSubmit } = window.MarketplaceForm;
    const box = form.querySelector('[data-attributes]');
    const hint = form.querySelector('[data-attributes-hint]');
    const brandSelect = form.querySelector('[data-brand-select]');
    const brandName = form.querySelector('[data-brand-name]');
    const brandHint = form.querySelector('[data-brand-hint]');
    const old = JSON.parse(form.dataset.oldAttributes || '{}') || {};

    const MULTI = [4, 5];
    const ALLOW_CUSTOM = [2, 3, 5];
    const DATE_TYPE = 4;

    function field(attr) {
        const base = `attributes[${attr.id}]`;
        const prev = old[attr.id] || {};
        const picked = (prev.values || []).map(String);
        const req = attr.mandatory && !ALLOW_CUSTOM.includes(attr.input_type) ? 'required' : '';
        const star = attr.mandatory ? ' <span class="text-danger">*</span>' : '';
        let html = '';

        if (attr.options.length && attr.input_type !== 3) {
            const multiple = MULTI.includes(attr.input_type);
            html += `<select name="${base}[values][]" class="form-select form-select-sm" ${multiple ? 'multiple size="4"' : ''} ${req}>`
                + (multiple ? '' : '<option value="">Pilih...</option>')
                + attr.options.map((o) => `<option value="${o.id}" ${picked.includes(String(o.id)) ? 'selected' : ''}>${esc(o.name)}</option>`).join('')
                + '</select>';
        }

        if (ALLOW_CUSTOM.includes(attr.input_type) || !attr.options.length) {
            const type = attr.validation === 1 || attr.validation === 3 ? 'number' : (attr.validation === DATE_TYPE ? 'date' : 'text');
            html += `<input type="${type}" step="any" name="${base}[custom]" class="form-control form-control-sm ${html ? 'mt-1' : ''}"
                placeholder="${attr.options.length ? 'atau isi sendiri' : ''}" value="${esc(prev.custom ?? '')}" ${attr.mandatory && !attr.options.length ? 'required' : ''}>`;
            if (attr.format_type === 2 && attr.units.length) {
                html += `<select name="${base}[unit]" class="form-select form-select-sm mt-1">${attr.units.map((u) => `<option ${u === prev.unit ? 'selected' : ''}>${esc(u)}</option>`).join('')}</select>`;
            }
        }

        return `<div class="col-12 col-md-6 ${attr.mandatory ? '' : 'mp-optional d-none'}"><label class="form-label small mb-1">${esc(attr.label)}${star}</label>${html}</div>`;
    }

    async function loadAttributes(categoryId) {
        box.innerHTML = '';
        hint.textContent = 'Memuat atribut kategori...';
        try {
            const attrs = (await getJson(form.dataset.attributesUrl.replace('__ID__', encodeURIComponent(categoryId)))).attributes || [];
            attrs.sort((a, b) => Number(b.mandatory) - Number(a.mandatory));
            box.innerHTML = attrs.map(field).join('');
            const optional = attrs.filter((a) => !a.mandatory).length;
            hint.innerHTML = attrs.length
                ? 'Isi atribut wajib (<span class="text-danger">*</span>).' + (optional ? ` <a href="#" data-toggle-optional>Tampilkan ${optional} atribut opsional</a>` : '')
                : 'Kategori ini tidak meminta atribut tambahan.';
        } catch (err) {
            hint.innerHTML = `<span class="text-danger">${esc(err.message)}</span>`;
        }
    }

    async function loadBrands(categoryId) {
        brandHint.textContent = 'Memuat daftar brand...';
        try {
            const data = await getJson(form.dataset.brandsUrl.replace('__ID__', encodeURIComponent(categoryId)));
            const keep = brandSelect.value || form.dataset.oldBrand || '0';
            brandSelect.innerHTML = '<option value="0">No Brand</option>'
                + (data.items || []).filter((b) => b.id !== 0).map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join('');
            brandSelect.value = [...brandSelect.options].some((o) => o.value === String(keep)) ? String(keep) : '0';
            syncBrandName();
            brandHint.textContent = data.mandatory
                ? 'Kategori ini mewajibkan brand. Produk tanpa merek: pilih No Brand.'
                : 'Brand opsional untuk kategori ini. Produk tanpa merek: pilih No Brand.';
        } catch (err) {
            brandHint.innerHTML = `<span class="text-danger">${esc(err.message)}</span>`;
        }
    }

    const syncBrandName = () => {
        brandName.value = brandSelect.value === '0' ? '' : brandSelect.selectedOptions[0]?.textContent || '';
    };
    brandSelect.addEventListener('change', syncBrandName);

    form.addEventListener('marketplace:category', (e) => {
        loadAttributes(e.detail.id);
        loadBrands(e.detail.id);
    });

    hint.addEventListener('click', (e) => {
        if (!e.target.matches('[data-toggle-optional]')) return;
        e.preventDefault();
        box.querySelectorAll('.mp-optional').forEach((el) => el.classList.toggle('d-none'));
    });

    // Atribut tanggal dikirim Shopee sebagai Unix timestamp (detik).
    form.addEventListener('submit', () => {
        box.querySelectorAll('input[type=date]').forEach((input) => {
            if (input.value) {
                input.type = 'text';
                input.value = String(Math.floor(new Date(input.value + 'T00:00:00Z').getTime() / 1000));
            }
        });
    });

    categoryPicker(form);
    lockOnSubmit(form, 'Mengunggah ke Shopee...');
})();
