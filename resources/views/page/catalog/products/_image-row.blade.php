@php($err = fn ($key) => $errors->first("images.{$i}.{$key}"))
<div class="d-flex gap-2 bg-white border rounded-3 shadow-sm p-2 mb-2" data-row>
    <span class="text-muted px-1 align-self-center" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
    <input type="hidden" name="images[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="flex-shrink-0">
        <img src="{{ $row['thumb'] ?? '' }}" alt="" width="72" height="72" class="rounded border {{ empty($row['thumb']) ? 'd-none' : '' }}" style="object-fit: cover;" data-preview>
        <div class="rounded border bg-light d-flex align-items-center justify-content-center {{ empty($row['thumb']) ? '' : 'd-none' }}" style="width: 72px; height: 72px;" data-placeholder>
            <i class="ri-image-add-line text-muted fs-4"></i>
        </div>
    </div>
    <div class="flex-grow-1 row g-2">
        @if (empty($row['id']))
            <div class="col-12">
                <input type="file" name="images[{{ $i }}][file]" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm @if ($err('file')) is-invalid @endif" data-file>
                @if ($err('file')) <div class="invalid-feedback">{{ $err('file') }}</div> @endif
            </div>
        @endif
        <div class="col-md-5">
            <input type="text" name="images[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="150" class="form-control form-control-sm" placeholder="Nama foto (opsional)">
        </div>
        <div class="col-md-4">
            <input type="text" name="images[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="255" class="form-control form-control-sm" placeholder="Keterangan (opsional)">
        </div>
        <div class="col-md-3">
            <select name="images[{{ $i }}][status]" class="form-select form-select-sm">
                @foreach (\App\Models\CatalogProductImage::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(($row['status'] ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-danger align-self-start" data-remove title="Hapus foto"><i class="ri-delete-bin-line"></i></button>
</div>
