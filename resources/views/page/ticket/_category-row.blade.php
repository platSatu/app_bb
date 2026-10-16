@php($err = fn ($key) => $errors->first("categories.{$i}.{$key}"))
<div class="bg-white border rounded-3 shadow-sm p-3 mb-3" data-row>
    <input type="hidden" name="categories[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
        <input type="text" name="categories[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="100" class="form-control form-control-sm fw-semibold @if ($err('name')) is-invalid @endif" placeholder="mis. VIP" required>
        <select name="categories[{{ $i }}][status]" class="form-select form-select-sm" style="max-width: 140px;">
            @foreach (\App\Models\TicketCategory::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($row['status'] ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus"><i class="ri-delete-bin-line"></i></button>
    </div>
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Harga (Rp)</label>
            <input type="number" name="categories[{{ $i }}][price]" min="0" value="{{ $row['price'] ?? '' }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Kuota (stok awal)</label>
            <input type="number" name="categories[{{ $i }}][quota]" min="1" value="{{ $row['quota'] ?? '' }}" class="form-control form-control-sm @if ($err('quota')) is-invalid @endif" required>
            @if ($err('quota')) <div class="invalid-feedback">{{ $err('quota') }}</div> @endif
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Maks/pembelian</label>
            <input type="number" name="categories[{{ $i }}][max_per_order]" min="1" max="100" value="{{ $row['max_per_order'] ?? 10 }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label small mb-1">Keterangan (opsional)</label>
            <input type="text" name="categories[{{ $i }}][description]" maxlength="255" value="{{ $row['description'] ?? '' }}" class="form-control form-control-sm" placeholder="mis. Termasuk merchandise">
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Penjualan dibuka (opsional)</label>
            <input type="datetime-local" name="categories[{{ $i }}][sale_starts_at]" value="{{ $row['sale_starts_at'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Penjualan ditutup (opsional)</label>
            <input type="datetime-local" name="categories[{{ $i }}][sale_ends_at]" value="{{ $row['sale_ends_at'] ?? '' }}" class="form-control form-control-sm @if ($err('sale_ends_at')) is-invalid @endif">
            @if ($err('sale_ends_at')) <div class="invalid-feedback">{{ $err('sale_ends_at') }}</div> @endif
        </div>
    </div>
</div>
