<div class="row g-3">
    <div class="col-md-6">
        <label for="page_type_id" class="form-label">Kategori Page</label>
        <select id="page_type_id" name="page_type_id" class="form-select @error('page_type_id') is-invalid @enderror" required>
            <option value="">-- Pilih kategori --</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" data-template="{{ $type->template }}" @selected(old('page_type_id', $page->page_type_id ?? request('type')) === $type->id)>
                    {{ $type->name }}{{ $type->isActive() ? '' : ' (tidak aktif)' }}
                </option>
            @endforeach
        </select>
        @error('page_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nama Page</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $page->name) }}" placeholder="mis. Pendaftaran Seminar Oktober" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="branch_id" class="form-label">Branch</label>
        <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_id', $page->branch_id) === $branch->id)>
                    {{ $branch->relationLoaded('company') && $branch->company ? $branch->company->name.' — ' : '' }}{{ $branch->name }}{{ $branch->isActive() ? '' : ' (tidak aktif)' }}
                </option>
            @endforeach
        </select>
        @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\PageCategory::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $page->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="slug" class="form-label">Link</label>
        <div class="input-group">
            <span class="input-group-text">{{ url('page') }}/</span>
            <input type="text" id="slug" name="slug" maxlength="100" class="form-control @error('slug') is-invalid @enderror"
                value="{{ old('slug', $page->slug) }}" placeholder="otomatis dari nama page">
        </div>
        <div class="form-text">
            Huruf kecil, angka, dan tanda minus saja.
            @if ($page->exists)
                <span class="text-warning">Kalau link diganti, QR code lama yang sudah dicetak tidak bisa dipakai lagi.</span>
            @endif
        </div>
        @error('slug') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="start_date" class="form-label">Tanggal Mulai (opsional)</label>
        <input type="date" id="start_date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $page->start_date?->format('Y-m-d')) }}">
        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="end_date" class="form-label">Tanggal Selesai (opsional)</label>
        <input type="date" id="end_date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $page->end_date?->format('Y-m-d')) }}">
        <div class="form-text">Kosongkan kalau page berlaku terus.</div>
        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @include('page.ticket._fee-payer')

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi (opsional)</label>
        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $page->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

{{-- Tampilan & SEO: semua opsional, yang kosong diisi otomatis. --}}
@php($seo = $page->seo())
<hr class="my-4">
<h6 class="mb-1">Tampilan & SEO</h6>
<p class="small text-muted mb-3">Semua boleh dikosongkan. Yang kosong otomatis memakai nama & deskripsi page, lalu banner, lalu gambar bawaan {{ config('app.name') }}.</p>

<div class="row g-3">
    <div class="col-lg-7">
        @foreach (['banner' => ['Banner header', 'Tampil paling atas di page. Disarankan 1920 × 1080 px, otomatis dikompres.', $page->bannerUrl()], 'og_image' => ['Gambar share', 'Muncul saat link dibagikan di WhatsApp/Facebook. Disarankan 1200 × 630 px.', $page->og_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image) : null]] as $field => [$label, $hint, $current])
            <div class="mb-3">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                @if ($current)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ $current }}" alt="" class="rounded border" style="height:56px;aspect-ratio:16/9;object-fit:cover">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_{{ $field }}" value="1" id="remove_{{ $field }}">
                            <label class="form-check-label small" for="remove_{{ $field }}">Hapus</label>
                        </div>
                    </div>
                @endif
                <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,image/webp" class="form-control @error($field) is-invalid @enderror" data-preview-image>
                <div class="form-text">{{ $hint }} Maks {{ $field === 'banner' ? 5 : 3 }} MB.</div>
                @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach

        <div class="mb-3">
            <label class="form-label d-flex justify-content-between" for="meta_title">Judul SEO <span class="small text-muted" data-count-for="meta_title"></span></label>
            <input type="text" id="meta_title" name="meta_title" maxlength="70" class="form-control @error('meta_title') is-invalid @enderror" value="{{ old('meta_title', $page->meta_title) }}" placeholder="Kosongkan: pakai nama page">
            @error('meta_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="form-label d-flex justify-content-between" for="meta_description">Deskripsi SEO <span class="small text-muted" data-count-for="meta_description"></span></label>
            <textarea id="meta_description" name="meta_description" maxlength="160" rows="2" class="form-control @error('meta_description') is-invalid @enderror" placeholder="Kosongkan: pakai deskripsi page">{{ old('meta_description', $page->meta_description) }}</textarea>
            @error('meta_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="form-label" for="ga_id">Google Analytics (opsional)</label>
            <input type="text" id="ga_id" name="ga_id" maxlength="20" class="form-control text-uppercase @error('ga_id') is-invalid @enderror" value="{{ old('ga_id', $page->ga_id) }}" placeholder="G-XXXXXXXXXX">
            <div class="form-text">Measurement ID dari Google Analytics 4 milik Anda, supaya statistik page masuk ke akun Analytics Anda juga.</div>
            @error('ga_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="col-lg-5">
        <div class="small text-muted mb-2">Pratinjau saat dibagikan</div>
        <div class="border rounded-3 overflow-hidden bg-light">
            <img src="{{ $seo['image'] }}" alt="" class="w-100" style="aspect-ratio:1.91/1;object-fit:cover" id="seo-preview-image">
            <div class="p-3 bg-white">
                <div class="small text-muted text-truncate">{{ parse_url(url('/'), PHP_URL_HOST) }}</div>
                <div class="fw-semibold text-truncate" id="seo-preview-title">{{ $seo['title'] }}</div>
                <div class="small text-muted" id="seo-preview-description" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $seo['description'] }}</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            var val = function (id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
            var refresh = function () {
                document.getElementById('seo-preview-title').textContent = val('meta_title') || val('name') || 'Nama page';
                document.getElementById('seo-preview-description').textContent = val('meta_description') || val('description') || val('name');
                document.querySelectorAll('[data-count-for]').forEach(function (el) {
                    var input = document.getElementById(el.dataset.countFor);
                    el.textContent = input.value.length + '/' + input.maxLength;
                });
            };
            ['name', 'description', 'meta_title', 'meta_description'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', refresh);
            });
            // Gambar share dipilih: dipakai di pratinjau; banner dipakai kalau gambar share kosong.
            document.querySelectorAll('[data-preview-image]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var share = document.getElementById('og_image');
                    var file = (share.files[0]) || (input.id === 'banner' ? input.files[0] : null);
                    if (file) document.getElementById('seo-preview-image').src = URL.createObjectURL(file);
                });
            });
            refresh();
        })();
    </script>
@endpush

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route($routes.'.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
