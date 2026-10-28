@extends('layouts.dashboard')

@section('title', 'Konten Landing Page')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php
    $rows = old('sections', $sections->map(fn ($s) => ['id' => $s->id, 'type' => $s->type, 'status' => $s->status, 'data' => $s->data])->all());
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'landing-sections'])

    <div class="row g-3">
        <div class="col-xl-6">
            @if ($canManage)
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3">
                        <h6 class="mb-1 fw-semibold"><i class="ri-sparkling-2-line text-primary me-1"></i>Buat dengan AI</h6>
                        <div class="text-muted small lh-sm">
                            Ceritakan bisnis Anda, AI menyusun isi section. Hasilnya bisa diedit dan <strong>belum tersimpan</strong> sampai Anda klik Simpan Semua.
                            @if (! is_null($aiRemaining)) Sisa kuota hari ini: <strong id="ai-remaining">{{ $aiRemaining }}</strong>x. @endif
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <textarea id="ai-description" rows="3" maxlength="1500" class="form-control mb-2"
                            placeholder="mis. Kursus musik anak di Bogor, kelas piano, gitar, vokal. Harga mulai 350rb/bulan. Buka Senin–Sabtu 10.00–19.00."></textarea>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-primary btn-sm" id="ai-generate"><i class="ri-sparkling-2-line me-1"></i>Buat dengan AI</button>
                            <span class="small text-muted" id="ai-status"></span>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route($routes.'.landing.sections.sync', $page) }}" enctype="multipart/form-data" id="landing-form">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h6 class="mb-1 fw-semibold">Section</h6>
                            <div class="text-muted small lh-sm">Geser <i class="ri-draggable"></i> untuk mengurutkan. Isi berupa teks biasa, aman ditampilkan.</div>
                        </div>
                        @if ($canManage)
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Semua</button>
                        @endif
                    </div>
                    <div class="card-body bg-light p-3 p-md-4">
                        @if ($errors->any())
                            <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
                        @endif
                        <fieldset @disabled(! $canManage)>
                            <div data-sortable id="section-rows">
                                @foreach ($rows as $i => $row)
                                    @include('page.landing._section-row')
                                @endforeach
                            </div>
                            <div id="section-empty" class="text-center text-muted py-4 {{ count($rows) ? 'd-none' : '' }}">
                                <i class="ri-layout-4-line fs-2 d-block mb-1"></i>Belum ada section. Coba Buat dengan AI, atau tambah section di bawah.
                            </div>
                        </fieldset>
                        @if ($canManage)
                            <div class="d-flex gap-2 mt-2">
                                <select id="section-type" class="form-select">
                                    @foreach (\App\Models\LandingSection::TYPES as $type => [$label])
                                        <option value="{{ $type }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-primary text-nowrap" id="add-section"><i class="ri-add-line"></i> Tambah Section</button>
                            </div>
                            @foreach (array_keys(\App\Models\LandingSection::TYPES) as $type)
                                <template data-template="{{ $type }}">@include('page.landing._section-row', ['i' => '__INDEX__', 'row' => ['type' => $type]])</template>
                            @endforeach
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-6">
            <div class="card position-sticky" style="top: 90px;">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-1 fw-semibold">Preview</h6>
                        <div class="text-muted small lh-sm">Foto baru tampil setelah disimpan.</div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary active" data-device="100%"><i class="ri-computer-line"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-device="390px"><i class="ri-smartphone-line"></i></button>
                    </div>
                </div>
                <div class="card-body bg-light p-2 text-center">
                    <iframe id="preview-frame" title="Preview" class="bg-white border rounded" style="width: 100%; height: 70vh;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var form = document.getElementById('landing-form');
            var rows = document.getElementById('section-rows');
            var frame = document.getElementById('preview-frame');
            var csrf = form.querySelector('[name=_token]').value;
            var counter = 0, timer = null;

            function refreshEmpty() { document.getElementById('section-empty').classList.toggle('d-none', rows.children.length > 0); }

            function preview() {
                var data = new FormData(form);
                data.delete('_method');
                // File tidak ikut dikirim ke preview.
                Array.from(data.keys()).forEach(function (key) { if (data.get(key) instanceof File) data.delete(key); });
                fetch(@json(route($routes.'.landing.preview', $page)), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf }, body: data })
                    .then(function (r) { return r.ok ? r.text() : '<p style="padding:2rem;font-family:sans-serif">Preview belum bisa ditampilkan, periksa isian.</p>'; })
                    .then(function (html) { frame.srcdoc = html; });
            }
            function schedule() { clearTimeout(timer); timer = setTimeout(preview, 700); }

            document.getElementById('add-section')?.addEventListener('click', function () {
                var template = document.querySelector('[data-template="' + document.getElementById('section-type').value + '"]');
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
                refreshEmpty(); schedule();
            });

            rows.addEventListener('click', function (event) {
                if (event.target.closest('[data-remove]')) {
                    if (!confirm('Hapus section ini? Terhapus setelah Simpan Semua.')) return;
                    event.target.closest('[data-row]').remove();
                } else if (event.target.closest('[data-remove-gallery]')) {
                    event.target.closest('[data-gallery-item]').remove();
                } else { return; }
                refreshEmpty(); schedule();
            });
            rows.addEventListener('input', schedule);
            rows.addEventListener('change', schedule);
            rows.addEventListener('sorted', schedule);

            document.querySelectorAll('[data-device]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('[data-device]').forEach(function (b) { b.classList.remove('active'); });
                    button.classList.add('active');
                    frame.style.width = button.dataset.device;
                });
            });

            var aiButton = document.getElementById('ai-generate');
            aiButton?.addEventListener('click', function () {
                var status = document.getElementById('ai-status');
                if (rows.children.length && !confirm('Isi section yang ada akan diganti hasil AI (belum tersimpan). Lanjutkan?')) return;
                aiButton.disabled = true;
                status.textContent = 'AI sedang menulis, biasanya 10–30 detik…';
                fetch(@json(route($routes.'.landing.generate', $page)), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ description: document.getElementById('ai-description').value })
                }).then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
                .then(function (res) {
                    if (!res.ok) { status.textContent = res.body.message || (res.body.errors && Object.values(res.body.errors)[0][0]) || 'Gagal membuat konten.'; return; }
                    rows.innerHTML = res.body.html;
                    status.textContent = res.body.count + ' section dibuat. Periksa & edit, lalu klik Simpan Semua.';
                    if (document.getElementById('ai-remaining') && res.body.remaining !== null) document.getElementById('ai-remaining').textContent = res.body.remaining;
                    refreshEmpty(); preview();
                })
                .catch(function () { status.textContent = 'Koneksi terputus, coba lagi.'; })
                .finally(function () { aiButton.disabled = false; });
            });

            preview();
        })();
    </script>
@endsection
