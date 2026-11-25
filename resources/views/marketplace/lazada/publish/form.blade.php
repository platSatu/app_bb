@extends('layouts.dashboard')

@section('title', 'Upload ke Lazada')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('marketplace.lazada._nav', ['active' => 'publish'])

    @php
        $cover = $product->cover();
        $activeImages = $product->images->where('status', \App\Models\CatalogProductImage::STATUS_ACTIVE);
    @endphp

    <form method="POST" action="{{ route($routes.'.marketplace.lazada.publish.store', [$page, $store, $product]) }}" id="lzd-publish"
        data-categories-url="{{ route($routes.'.marketplace.lazada.categories', [$page, $store]) }}"
        data-suggest-url="{{ route($routes.'.marketplace.lazada.categories.suggest', [$page, $store]) }}"
        data-attributes-url="{{ route($routes.'.marketplace.lazada.categories.attributes', [$page, $store, '__ID__']) }}"
        data-product-name="{{ $product->name }}"
        data-product-image="{{ $cover ? url($cover->url()) : '' }}"
        data-old-attributes='@json(old('attributes', []))'
        data-old-sku-attributes='@json(old('sku_attributes', []))'
        onsubmit="this.querySelector('[type=submit]').disabled = true; this.querySelector('[type=submit]').innerHTML = 'Mengunggah ke Lazada...';">
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif
        @if ($lastListing && ! $lastListing->isSubmitted())
            <div class="alert alert-warning small mb-3"><b>Percobaan terakhir gagal:</b> {{ $lastListing->error }}</div>
        @endif

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-3">
                            @if ($cover)
                                <img src="{{ $cover->thumbnailUrl() }}" alt="" width="72" height="72" class="rounded border" style="object-fit: cover;">
                            @endif
                            <div>
                                <h5 class="mb-1">{{ $product->name }}</h5>
                                <div class="small text-muted">{{ $activeImages->count() }} foto aktif (maks. 8 dikirim) · nama, deskripsi & foto diambil dari produk katalog.</div>
                            </div>
                        </div>

                        <h6 class="mb-2">1. Kategori Lazada</h6>
                        <input type="hidden" name="category_id" value="{{ old('category_id') }}" data-category-id>
                        <div class="border rounded p-2 mb-2 bg-light-subtle small" data-category-label>{{ old('category_id') ? 'Kategori ID '.old('category_id') : 'Belum dipilih' }}</div>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" placeholder="Cari kategori, mis. kaos, sepatu, skincare" data-category-search>
                            <button type="button" class="btn btn-outline-secondary" data-category-find><i class="ri-search-line"></i> Cari</button>
                            <button type="button" class="btn btn-outline-primary" data-category-suggest><i class="ri-magic-line"></i> Saran dari nama produk</button>
                        </div>
                        <div class="list-group small mb-3" data-category-results style="max-height: 260px; overflow-y: auto"></div>

                        <h6 class="mb-2">2. Atribut kategori</h6>
                        <div class="small text-muted mb-2" data-attributes-hint>Pilih kategori dulu, atribut yang diminta Lazada akan muncul di sini. Yang bertanda <span class="text-danger">*</span> wajib.</div>
                        <div class="row g-2 mb-3" data-attributes></div>

                        <h6 class="mb-2">3. Brand</h6>
                        <div class="row g-2 mb-1">
                            <div class="col-12 col-md-6">
                                <label class="form-label small mb-1">Brand ID Lazada <span class="text-danger">*</span></label>
                                <input type="text" name="brand_id" class="form-control" inputmode="numeric" value="{{ old('brand_id') }}" required placeholder="mis. ID untuk No Brand">
                            </div>
                        </div>
                        <div class="form-text mb-3">Produk tanpa merek: pakai Brand ID "No Brand" di negara toko ini (lihat di Seller Center saat menambah produk, atau tanyakan ke Lazada). API Lazada tidak menyediakan pencarian brand berdasarkan nama.</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">4. Harga & stok</h6>
                        <div class="mb-2">
                            <label class="form-label small mb-1">SKU penjual <span class="text-danger">*</span></label>
                            <input type="text" name="seller_sku" class="form-control" maxlength="100" value="{{ old('seller_sku', $defaultSku) }}" required>
                            <div class="form-text">Harus unik di toko Lazada ini.</div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" name="price" class="form-control" min="1000" value="{{ old('price', $product->price) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Harga promo (Rp)</label>
                                <input type="number" name="special_price" class="form-control" min="1000" value="{{ old('special_price', $product->isDiscounted() ? $product->sale_price : null) }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1">Stok di Lazada <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0" value="{{ old('quantity', $product->stock ?? 10) }}" required>
                            @unless ($product->tracksStock())
                                <div class="form-text">Stok katalog tidak dibatasi; Lazada butuh angka pasti.</div>
                            @endunless
                        </div>

                        <h6 class="mb-2">5. Paket</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Berat (kg) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="package_weight" class="form-control" min="0.01" value="{{ old('package_weight', '0.5') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Panjang (cm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="package_length" class="form-control" min="1" value="{{ old('package_length', 10) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Lebar (cm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="package_width" class="form-control" min="1" value="{{ old('package_width', 10) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Tinggi (cm) <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" name="package_height" class="form-control" min="1" value="{{ old('package_height', 10) }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">Isi paket</label>
                                <input type="text" name="package_content" class="form-control" maxlength="200" value="{{ old('package_content') }}" placeholder="mis. 1 pcs kaos">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100 mt-3"><i class="ri-upload-2-line"></i> Upload ke Lazada</button>
                        <div class="form-text">Foto disalin ke server Lazada dulu, jadi alamat foto bizbos harus bisa dibuka dari internet (tidak bisa dari localhost).</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('lzd-publish');
            const $ = (sel) => form.querySelector(sel);
            const results = $('[data-category-results]');
            const attrBox = $('[data-attributes]');
            const hint = $('[data-attributes-hint]');
            const oldAttrs = JSON.parse(form.dataset.oldAttributes || '{}') || {};
            const oldSkuAttrs = JSON.parse(form.dataset.oldSkuAttributes || '{}') || {};
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

            async function getJson(url) {
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Gagal memuat data dari Lazada.');
                return data;
            }

            function showCategories(list) {
                results.innerHTML = list.length
                    ? list.map((c) => `<button type="button" class="list-group-item list-group-item-action" data-id="${esc(c.id)}" data-path="${esc(c.path || c.name)}">${esc(c.path || c.name)} <span class="text-muted">#${esc(c.id)}</span></button>`).join('')
                    : '<div class="list-group-item text-muted">Kategori tidak ditemukan. Coba kata lain.</div>';
            }

            async function run(btn, fn) {
                const label = btn.innerHTML;
                btn.disabled = true; btn.innerHTML = 'Memuat...';
                try { await fn(); } catch (e) { results.innerHTML = `<div class="list-group-item text-danger">${esc(e.message)}</div>`; }
                btn.disabled = false; btn.innerHTML = label;
            }

            $('[data-category-find]').addEventListener('click', (e) => run(e.currentTarget, async () => {
                const q = $('[data-category-search]').value.trim();
                const data = await getJson(form.dataset.categoriesUrl + '?q=' + encodeURIComponent(q));
                showCategories(data.categories || []);
            }));
            $('[data-category-search]').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('[data-category-find]').click(); } });

            $('[data-category-suggest]').addEventListener('click', (e) => run(e.currentTarget, async () => {
                const params = new URLSearchParams({ name: form.dataset.productName });
                if (form.dataset.productImage) params.set('image', form.dataset.productImage);
                const data = await getJson(form.dataset.suggestUrl + '?' + params);
                showCategories(data.categories || []);
            }));

            results.addEventListener('click', (e) => {
                const item = e.target.closest('[data-id]');
                if (!item) return;
                selectCategory(item.dataset.id, item.dataset.path);
                results.innerHTML = '';
            });

            function field(attr, group, old) {
                const name = `${group}[${attr.name}]`;
                const value = old[attr.name] ?? '';
                const req = attr.is_mandatory ? 'required' : '';
                const star = attr.is_mandatory ? ' <span class="text-danger">*</span>' : '';
                let input;
                if (attr.options.length && attr.input_type === 'multiSelect') {
                    const vals = Array.isArray(value) ? value : String(value).split(',');
                    input = `<select name="${esc(name)}[]" class="form-select form-select-sm" multiple size="4" ${req}>${attr.options.map((o) => `<option ${vals.includes(o) ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
                } else if (attr.options.length) {
                    input = `<select name="${esc(name)}" class="form-select form-select-sm" ${req}><option value="">Pilih...</option>${attr.options.map((o) => `<option ${o === value ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
                } else if (attr.input_type === 'richText') {
                    input = `<textarea name="${esc(name)}" class="form-control form-control-sm" rows="2" ${req}>${esc(value)}</textarea>`;
                } else {
                    input = `<input type="${attr.input_type === 'numeric' ? 'number' : 'text'}" step="any" name="${esc(name)}" class="form-control form-control-sm" value="${esc(value)}" ${req}>`;
                }
                return `<div class="col-12 col-md-6 ${attr.is_mandatory ? '' : 'lzd-optional d-none'}"><label class="form-label small mb-1">${esc(attr.label)}${star}</label>${input}</div>`;
            }

            async function selectCategory(id, path) {
                $('[data-category-id]').value = id;
                $('[data-category-label]').textContent = `${path} (#${id})`;
                attrBox.innerHTML = '';
                hint.textContent = 'Memuat atribut kategori...';
                try {
                    const data = await getJson(form.dataset.attributesUrl.replace('__ID__', encodeURIComponent(id)));
                    const attrs = data.attributes || [];
                    attrBox.innerHTML = attrs.map((a) => field(a, a.attribute_type === 'sku' ? 'sku_attributes' : 'attributes', a.attribute_type === 'sku' ? oldSkuAttrs : oldAttrs)).join('');
                    const optional = attrs.filter((a) => !a.is_mandatory).length;
                    hint.innerHTML = attrs.length
                        ? `Isi atribut wajib (<span class="text-danger">*</span>).` + (optional ? ` <a href="#" data-toggle-optional>Tampilkan ${optional} atribut opsional</a>` : '')
                        : 'Kategori ini tidak meminta atribut tambahan.';
                } catch (e) {
                    hint.innerHTML = `<span class="text-danger">${esc(e.message)}</span>`;
                }
            }

            hint.addEventListener('click', (e) => {
                if (!e.target.matches('[data-toggle-optional]')) return;
                e.preventDefault();
                attrBox.querySelectorAll('.lzd-optional').forEach((el) => el.classList.toggle('d-none'));
            });

            // Kembali dari validasi gagal: muat ulang atribut kategori yang sudah dipilih.
            if ($('[data-category-id]').value) {
                selectCategory($('[data-category-id]').value, 'Kategori');
            }
        })();
    </script>
@endpush
