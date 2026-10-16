<div class="d-flex gap-2 align-items-center bg-white border rounded-3 shadow-sm p-2 mb-2 flex-wrap flex-md-nowrap" data-row>
    <span class="text-muted px-1" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
    <input type="text" name="links[{{ $i }}][label]" value="{{ $link['label'] ?? '' }}" maxlength="60" class="form-control form-control-sm" placeholder="Teks tombol, mis. Instagram" required data-preview-input>
    <select name="links[{{ $i }}][page_id]" class="form-select form-select-sm" style="max-width: 220px;">
        <option value="">— Link luar —</option>
        @foreach ($branchPages as $target)
            <option value="{{ $target->id }}" @selected(($link['page_id'] ?? null) === $target->id)>{{ $target->name }} ({{ $target->type?->name }})</option>
        @endforeach
    </select>
    <input type="url" name="links[{{ $i }}][url]" value="{{ $link['url'] ?? '' }}" maxlength="500" class="form-control form-control-sm" placeholder="https://instagram.com/...">
    <div class="form-check form-switch mb-0" title="Tampilkan">
        <input type="hidden" name="links[{{ $i }}][active]" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="links[{{ $i }}][active]" value="1" @checked($link['active'] ?? true)>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus"><i class="ri-delete-bin-line"></i></button>
</div>
