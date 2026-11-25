/*
 * Form upload produk marketplace: helper bersama + pemilih kategori.
 * Dipakai: resources/views/marketplace/{channel}/publish-form.blade.php
 * Form wajib punya data-categories-url, data-suggest-url, data-product-name, data-product-image.
 * Saat kategori dipilih, form memicu event "marketplace:category" dengan detail {id, path}.
 */
window.MarketplaceForm = (function () {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function getJson(url) {
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Gagal memuat data dari marketplace.');
        return data;
    }

    async function busy(btn, fn) {
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Memuat...';
        try { await fn(); } finally { btn.disabled = false; btn.innerHTML = label; }
    }

    function categoryPicker(form) {
        const $ = (sel) => form.querySelector(sel);
        const results = $('[data-category-results]');

        const show = (list) => {
            results.innerHTML = list.length
                ? list.map((c) => `<button type="button" class="list-group-item list-group-item-action" data-id="${esc(c.id)}" data-path="${esc(c.path || c.name)}">${esc(c.path || c.name)} <span class="text-muted">#${esc(c.id)}</span></button>`).join('')
                : '<div class="list-group-item text-muted">Kategori tidak ditemukan. Coba kata lain.</div>';
        };
        const fail = (e) => { results.innerHTML = `<div class="list-group-item text-danger">${esc(e.message)}</div>`; };

        const select = (id, path) => {
            $('[data-category-id]').value = id;
            $('[data-category-label]').textContent = `${path} (#${id})`;
            results.innerHTML = '';
            form.dispatchEvent(new CustomEvent('marketplace:category', { detail: { id, path } }));
        };

        $('[data-category-find]').addEventListener('click', (e) => busy(e.currentTarget, async () => {
            const q = $('[data-category-search]').value.trim();
            show((await getJson(form.dataset.categoriesUrl + '?q=' + encodeURIComponent(q))).categories || []);
        }).catch(fail));

        $('[data-category-search]').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); $('[data-category-find]').click(); }
        });

        $('[data-category-suggest]').addEventListener('click', (e) => busy(e.currentTarget, async () => {
            const params = new URLSearchParams({ name: form.dataset.productName });
            if (form.dataset.productImage) params.set('image', form.dataset.productImage);
            show((await getJson(form.dataset.suggestUrl + '?' + params)).categories || []);
        }).catch(fail));

        results.addEventListener('click', (e) => {
            const item = e.target.closest('[data-id]');
            if (item) select(item.dataset.id, item.dataset.path);
        });

        // Kembali dari validasi gagal: muat ulang data kategori yang sudah dipilih.
        const current = $('[data-category-id]').value;
        if (current) {
            setTimeout(() => form.dispatchEvent(new CustomEvent('marketplace:category', { detail: { id: current, path: 'Kategori' } })), 0);
        }
    }

    /** Matikan tombol submit saat dikirim supaya tidak terkirim dua kali. */
    function lockOnSubmit(form, text) {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type=submit]');
            btn.disabled = true;
            btn.innerHTML = text;
        });
    }

    return { esc, getJson, busy, categoryPicker, lockOnSubmit };
})();
