{{-- Satu section di editor landing. $i = indeks, $row = [id, type, status, data]. --}}
@php
    $type = $row['type'] ?? 'hero';
    [$typeLabel, $fields, $media] = \App\Models\LandingSection::TYPES[$type];
    $data = $row['data'] ?? [];
    $name = fn ($key) => "sections[{$i}][{$key}]";
    $img = fn ($path) => \Illuminate\Support\Facades\Storage::disk('public')->url($path);
@endphp
<div class="bg-white border rounded-3 shadow-sm p-3 mb-3" data-row>
    <input type="hidden" name="{{ $name('id') }}" value="{{ $row['id'] ?? '' }}">
    <input type="hidden" name="{{ $name('type') }}" value="{{ $type }}">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="text-muted" data-drag-handle style="cursor: grab; touch-action: none;" title="Geser"><i class="ri-draggable fs-5"></i></span>
        <span class="fw-semibold flex-grow-1">{{ $typeLabel }}</span>
        <select name="{{ $name('status') }}" class="form-select form-select-sm" style="max-width: 140px;">
            @foreach (\App\Models\LandingSection::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(($row['status'] ?? 'active') === $value)>{{ $label === 'Aktif' ? 'Tampil' : 'Disembunyikan' }}</option>
            @endforeach
        </select>
        @if ($canManage)
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Hapus section"><i class="ri-delete-bin-line"></i></button>
        @endif
    </div>

    <div class="row g-2">
        @foreach ($fields as $field => [$label, $max, $multiline])
            <div class="{{ $multiline ? 'col-12' : 'col-md-6' }}">
                <label class="form-label small mb-1">{{ $label }}</label>
                @if ($multiline)
                    <textarea name="sections[{{ $i }}][data][{{ $field }}]" rows="{{ $field === 'items' ? 4 : 2 }}" maxlength="{{ $max }}" class="form-control form-control-sm" data-preview-input>{{ $data[$field] ?? '' }}</textarea>
                @else
                    <input type="text" name="sections[{{ $i }}][data][{{ $field }}]" maxlength="{{ $max }}" value="{{ $data[$field] ?? '' }}" class="form-control form-control-sm" data-preview-input>
                @endif
            </div>
        @endforeach

        @if (in_array($type, \App\Models\LandingSection::WITH_BUTTON, true))
            <div class="col-md-6">
                <label class="form-label small mb-1">Tombol menuju page</label>
                <select name="{{ $name('button_page_id') }}" class="form-select form-select-sm">
                    <option value="">— Pakai link luar —</option>
                    @foreach ($branchPages as $target)
                        <option value="{{ $target->id }}" @selected(($data['button_page_id'] ?? null) === $target->id)>{{ $target->name }} ({{ $target->type?->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small mb-1">atau link luar</label>
                <input type="url" name="{{ $name('button_url') }}" value="{{ $data['button_url'] ?? '' }}" maxlength="500" class="form-control form-control-sm" placeholder="https://...">
            </div>
        @endif

        @if ($media === 'image')
            <div class="col-12 d-flex align-items-center gap-2">
                @if (! empty($data['image']))
                    <input type="hidden" name="{{ $name('image') }}" value="{{ $data['image'] }}">
                    <img src="{{ $img($data['image']) }}" alt="" width="64" height="48" class="rounded border" style="object-fit: cover;">
                    <label class="small text-nowrap"><input type="checkbox" name="{{ $name('remove_image') }}" value="1"> Hapus</label>
                @endif
                <input type="file" name="{{ $name('image_file') }}" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
            </div>
        @elseif ($media === 'images')
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach ((array) ($data['images'] ?? []) as $path)
                        <div class="position-relative" data-gallery-item>
                            <input type="hidden" name="sections[{{ $i }}][images][]" value="{{ $path }}">
                            <img src="{{ $img($path) }}" alt="" width="72" height="72" class="rounded border" style="object-fit: cover;">
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1 lh-1" data-remove-gallery title="Hapus foto">×</button>
                        </div>
                    @endforeach
                </div>
                <input type="file" name="sections[{{ $i }}][image_files][]" accept="image/jpeg,image/png,image/webp" multiple class="form-control form-control-sm">
                <div class="form-text">Maksimal {{ \App\Models\LandingSection::MAX_GALLERY }} foto, 5 MB per foto.</div>
            </div>
        @endif
    </div>
</div>
