{{--
    Pemilih kategori leaf (cari / saran dari nama produk). Script: public/js/marketplace/category-picker.js.
    Saat kategori dipilih, form memicu event "marketplace:category" ({id, path}) yang dipakai script atribut channel.
--}}
<input type="hidden" name="category_id" value="{{ old('category_id') }}" data-category-id>
<div class="border rounded p-2 mb-2 bg-light-subtle small" data-category-label>{{ old('category_id') ? 'Kategori #'.old('category_id') : 'Belum dipilih' }}</div>
<div class="input-group mb-2">
    <input type="text" class="form-control" placeholder="Cari kategori, mis. kaos, sepatu, skincare" data-category-search>
    <button type="button" class="btn btn-outline-secondary" data-category-find><i class="ri-search-line"></i> Cari</button>
    <button type="button" class="btn btn-outline-primary" data-category-suggest><i class="ri-magic-line"></i> Saran dari nama produk</button>
</div>
<div class="list-group small mb-3" data-category-results style="max-height: 260px; overflow-y: auto"></div>
