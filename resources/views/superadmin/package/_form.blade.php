<div class="row g-3">
    <div class="col-md-6">
        <label for="category_application_id" class="form-label">Kategori Aplikasi</label>
        <select id="category_application_id" name="category_application_id" class="form-select @error('category_application_id') is-invalid @enderror" required>
            <option value="">-- Pilih kategori --</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_application_id', $package->category_application_id) === $category->id)>
                    {{ $category->name }}{{ $category->isActive() ? '' : ' (tidak aktif)' }}
                </option>
            @endforeach
        </select>
        @error('category_application_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Nama Package</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $package->name) }}" placeholder="mis. Basic, Pro, Bisnis" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="price" class="form-label">Harga (Rp)</label>
        <input type="number" id="price" name="price" min="0" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $package->price) }}" required>
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="duration" class="form-label">Durasi</label>
        <div class="input-group">
            <input type="number" id="duration" name="duration" min="1" class="form-control @error('duration') is-invalid @enderror" value="{{ old('duration', $package->duration) }}" required>
            <span class="input-group-text">hari</span>
        </div>
        @error('duration') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Models\Package::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $package->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label">Layanan yang dibuka</label>
        <div class="form-text mt-0 mb-2">Centang satu untuk paket terpisah, atau beberapa untuk paket lengkap. Layanan yang tidak dicentang tetap terkunci.</div>
        @php($selectedApps = old('apps', $package->apps ?? []))
        <div class="row g-2">
            @foreach (\App\Support\Apps::LIST as $key => $app)
                <div class="col-sm-6 col-lg">
                    <input type="checkbox" class="btn-check" name="apps[]" value="{{ $key }}" id="app-{{ $key }}" @checked(in_array($key, $selectedApps, true))>
                    <label class="btn btn-outline-primary w-100 h-100 text-start p-3" for="app-{{ $key }}">
                        <i class="{{ $app['icon'] }} fs-4 d-block mb-1"></i>
                        <span class="fw-semibold d-block">{{ $app['label'] }}</span>
                        <span class="small opacity-75">{{ $app['hint'] }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        @error('apps') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
        @error('apps.*') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- Batas paket (App\Services\Subscription\PlanLimits). Kosong = tanpa batas; berlaku per branch. --}}
    <div class="col-12">
        <label class="form-label">Batas Paket</label>
        <div class="form-text mt-0 mb-2">Kosongkan = tanpa batas, 0 = tidak boleh sama sekali. Berlaku per branch, hanya untuk layanan yang dicentang di atas. Invoice & pesan WhatsApp dihitung per bulan kalender (reset tanggal 1).</div>
        <div class="row g-3">
            {{-- Event = kategori bertemplate tiket, Pages = kategori lain (App\Support\Apps::forTemplate). --}}
            @foreach (['events' => ['ri-coupon-3-line', 'Event · maksimal event per kategori', 'event'], 'pages' => ['ri-pages-line', 'Pages · maksimal page per kategori', 'page']] as $app => [$icon, $label, $unit])
                <div class="col-lg-4">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold mb-2"><i class="{{ $icon }} me-1"></i>{{ $label }}</div>
                        @forelse ($pageTypes->filter(fn ($type) => \App\Support\Apps::forTemplate($type->template) === $app) as $type)
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text flex-grow-1">{{ $type->name }}{{ $type->isActive() ? '' : ' (nonaktif)' }}</span>
                                <input type="number" min="0" max="1000" name="limits[{{ $app }}][{{ $type->id }}]" class="form-control text-end @error('limits.'.$app.'.'.$type->id) is-invalid @enderror" style="max-width: 110px"
                                    value="{{ old('limits.'.$app.'.'.$type->id, $package->limits[$app][$type->id] ?? '') }}" placeholder="∞">
                                <span class="input-group-text">{{ $unit }}</span>
                            </div>
                        @empty
                            <div class="small text-muted">Belum ada kategori {{ $unit }}.</div>
                        @endforelse
                        @error('limits.'.$app.'.*') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>
                </div>
            @endforeach
            <div class="col-lg-4">
                <div class="border rounded-3 p-3 h-100">
                    @foreach (['invoices' => ['ri-file-list-3-line', 'Tagihan · invoice dibuat per bulan', 'invoice'], 'whatsapp' => ['ri-whatsapp-line', 'WhatsApp · semua pesan keluar per bulan', 'pesan']] as $app => [$icon, $label, $unit])
                        <div class="fw-semibold mb-2"><i class="{{ $icon }} me-1"></i>{{ $label }}</div>
                        <div class="input-group input-group-sm {{ $loop->last ? '' : 'mb-3' }}">
                            <input type="number" min="0" max="{{ \App\Services\Subscription\PlanLimits::MAX_VALUE }}" name="limits[{{ $app }}][monthly]" class="form-control @error('limits.'.$app.'.monthly') is-invalid @enderror"
                                value="{{ old('limits.'.$app.'.monthly', $package->limits[$app]['monthly'] ?? '') }}" placeholder="Tanpa batas">
                            <span class="input-group-text">{{ $unit }} / bulan</span>
                        </div>
                        @error('limits.'.$app.'.monthly') <div class="text-danger fs-13 mb-2">{{ $message }}</div> @enderror
                    @endforeach
                    <div class="form-text">Pesan WhatsApp mencakup chat, broadcast, auto reply, chatbot, dan notifikasi. Kalau habis, semua pengiriman berhenti sampai tanggal 1.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_trial" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_trial" name="is_trial" value="1" @checked(old('is_trial', $package->is_trial))>
            <label class="form-check-label" for="is_trial">Paket trial</label>
        </div>
        <div class="form-text">Harga harus Rp 0. Setiap nomor HP dan email hanya bisa memakai trial satu kali.</div>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_featured" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_featured" name="is_featured" value="1" @checked(old('is_featured', $package->is_featured))>
            <label class="form-check-label" for="is_featured">Tandai Populer</label>
        </div>
        <div class="form-text">Tampil dengan badge "Populer" di katalog paket dan halaman harga website.</div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Fitur / Deskripsi</label>
        <textarea id="description" name="description" rows="6" class="form-control @error('description') is-invalid @enderror"
            placeholder="Tulis satu fitur per baris, mis.&#10;1 nomor WhatsApp&#10;Broadcast 1.000 pesan/bulan&#10;Auto reply">{{ old('description', $package->description) }}</textarea>
        <div class="form-text">Satu baris = satu poin di daftar fitur.</div>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('superadmin.packages.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
