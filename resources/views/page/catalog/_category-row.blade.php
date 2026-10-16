<div class="d-flex align-items-center gap-2 bg-white border rounded-3 shadow-sm p-2 mb-2" data-row>
    <span class="text-muted px-1" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
    <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <input type="text" name="categories[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="100" class="form-control form-control-sm" placeholder="mis. Minuman" required>
    <select name="categories[{{ $i }}][status]" class="form-select form-select-sm" style="max-width: 140px;">
        @foreach (\App\Models\CatalogCategory::STATUS_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(($row['status'] ?? 'active') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @isset($row['count'])
        <span class="badge bg-light text-dark text-nowrap">{{ $row['count'] }} produk</span>
    @endisset
    <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus"><i class="ri-delete-bin-line"></i></button>
</div>
