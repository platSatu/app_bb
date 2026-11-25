/*
 * Form upload ke TikTok Shop: atribut produk kategori & brand per kategori (bisa dicari).
 * Isian atribut: attributes[ID][values][] (id pilihan) dan attributes[ID][custom] (isi sendiri, bila is_customizable).
 * Butuh public/js/marketplace/category-picker.js.
 */
(function () {
    const form = document.getElementById('marketplace-publish');
    if (!form) return;

    const { esc, getJson, categoryPicker, lockOnSubmit } = window.MarketplaceForm;
    const box = form.querySelector('[data-attributes]');
    const hint = form.querySelector('[data-attributes-hint]');
    const brandSelect = form.querySelector('[data-brand-select]');
    const brandSearch = form.querySelector('[data-brand-search]');
    const brandHint = form.querySelector('[data-brand-hint]');
    const old = JSON.parse(form.dataset.oldAttributes || '{}') || {};
    let categoryId = form.querySelector('[data-category-id]').value;

    function field(attr) {
        const base = `attributes[${attr.id}]`;
        const prev = old[attr.id] || {};
        const picked = (prev.values || []).map(String);
        const star = attr.mandatory ? ' <span class="text-danger">*</span>' : '';
        let html = '';

        if (attr.options.length) {
            const required = attr.mandatory && !attr.customizable ? 'required' : '';
            html += `<select name="${base}[values][]" class="form-select form-select-sm" ${attr.multiple ? 'multiple size="4"' : ''} ${required}>`
                + (attr.multiple ? '' : '<option value="">Pilih...</option>')
                + attr.options.map((o) => `<option value="${esc(o.id)}" ${picked.includes(String(o.id)) ? 'selected' : ''}>${esc(o.name)}</option>`).join('')
                + '</select>';
        }

        if (attr.customizable || !attr.options.length) {
            html += `<input type="text" name="${base}[custom]" class="form-control form-control-sm ${html ? 'mt-1' : ''}"
                placeholder="${attr.options.length ? 'atau isi sendiri' : ''}" value="${esc(prev.custom ?? '')}" ${attr.mandatory && !attr.options.length ? 'required' : ''}>`;
        }

        return `<div class="col-12 col-md-6 ${attr.mandatory ? '' : 'mp-optional d-none'}"><label class="form-label small mb-1">${esc(attr.label)}${star}</label>${html}</div>`;
    }

    async function loadAttributes() {
        box.innerHTML = '';
        hint.textContent = 'Memuat atribut kategori...';
        try {
            const attrs = (await getJson(form.dataset.attributesUrl.replace('__ID__', encodeURIComponent(categoryId)))).attributes || [];
            box.innerHTML = attrs.map(field).join('');
            const optional = attrs.filter((a) => !a.mandatory).length;
            hint.innerHTML = attrs.length
                ? 'Isi atribut wajib (<span class="text-danger">*</span>).' + (optional ? ` <a href="#" data-toggle-optional>Tampilkan ${optional} atribut opsional</a>` : '')
                : 'Kategori ini tidak meminta atribut tambahan.';
        } catch (err) {
            hint.innerHTML = `<span class="text-danger">${esc(err.message)}</span>`;
        }
    }

    async function loadBrands() {
        if (!categoryId) return;
        brandHint.textContent = 'Memuat daftar brand...';
        try {
            const q = brandSearch.value.trim();
            const data = await getJson(form.dataset.brandsUrl.replace('__ID__', encodeURIComponent(categoryId)) + (q ? '?q=' + encodeURIComponent(q) : ''));
            const keep = brandSelect.value || form.dataset.oldBrand || '';
            brandSelect.innerHTML = '<option value="">Tanpa brand (No brand)</option>'
                + (data.items || []).map((b) => `<option value="${esc(b.id)}">${esc(b.name)}${b.authorized ? '' : ' (belum diotorisasi)'}</option>`).join('');
            brandSelect.value = [...brandSelect.options].some((o) => o.value === keep) ? keep : '';
            brandHint.textContent = (data.items || []).length
                ? 'Brand yang belum diotorisasi toko bisa ditolak saat audit. Produk tanpa merek: pilih "Tanpa brand".'
                : 'Brand tidak ditemukan. Produk tanpa merek: pilih "Tanpa brand".';
        } catch (err) {
            brandHint.innerHTML = `<span class="text-danger">${esc(err.message)}</span>`;
        }
    }

    let brandTimer;
    brandSearch.addEventListener('input', () => {
        clearTimeout(brandTimer);
        brandTimer = setTimeout(loadBrands, 400);
    });

    form.addEventListener('marketplace:category', (e) => {
        categoryId = e.detail.id;
        loadAttributes();
        loadBrands();
    });

    hint.addEventListener('click', (e) => {
        if (!e.target.matches('[data-toggle-optional]')) return;
        e.preventDefault();
        box.querySelectorAll('.mp-optional').forEach((el) => el.classList.toggle('d-none'));
    });

    categoryPicker(form);
    lockOnSubmit(form, 'Mengunggah ke TikTok Shop...');
})();
