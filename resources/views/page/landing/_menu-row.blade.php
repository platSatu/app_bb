<div class="d-flex gap-2 align-items-center bg-white border rounded-3 shadow-sm p-2 mb-2" data-row>
    <span class="text-muted px-1" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
    <input type="text" name="menu[{{ $i }}][label]" value="{{ $item['label'] ?? '' }}" maxlength="40" class="form-control form-control-sm" placeholder="Teks menu, mis. Toko" style="max-width: 180px;" required>
    <select name="menu[{{ $i }}][page_id]" class="form-select form-select-sm">
        <option value="">— Link luar —</option>
        @foreach ($branchPages as $target)
            <option value="{{ $target->id }}" @selected(($item['page_id'] ?? null) === $target->id)>{{ $target->name }} ({{ $target->type?->name }}){{ $target->isActive() ? '' : ' · tidak aktif' }}</option>
        @endforeach
    </select>
    <input type="url" name="menu[{{ $i }}][url]" value="{{ $item['url'] ?? '' }}" maxlength="500" class="form-control form-control-sm" placeholder="https://... (kalau link luar)">
    <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus"><i class="ri-delete-bin-line"></i></button>
</div>
