@extends('layouts.dashboard')

@section('title', 'Pengaturan AI')
@section('page-section', 'Superadmin')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Penyedia AI</h6>
                    <div class="text-muted small lh-sm">User memakai AI sebagai fasilitas bizbos. Hanya satu penyedia yang aktif; API key disimpan terenkripsi dan tidak pernah ditampilkan lagi.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    @forelse ($providers as $provider)
                        <details class="border rounded-3 p-3 mb-2 {{ $provider->is_active ? 'border-success' : '' }}">
                            <summary class="d-flex align-items-center justify-content-between gap-2 flex-wrap" style="list-style: none; cursor: pointer;">
                                <div>
                                    <span class="fw-semibold">{{ $provider->name }}</span>
                                    @if ($provider->is_active) <span class="badge bg-success-subtle text-success">Aktif dipakai</span> @endif
                                    <div class="small text-muted">{{ $provider->driverLabel() }} · {{ $provider->model }}</div>
                                    @if ($provider->isClassai())
                                        <div class="small" data-classai-balance="{{ route('superadmin.ai.account', $provider) }}"><span class="text-muted">Mengecek sisa saldo…</span></div>
                                    @endif
                                </div>
                                <div class="d-flex gap-1" onclick="event.stopPropagation()">
                                    <form method="POST" action="{{ route('superadmin.ai.test', $provider) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ri-plug-line"></i> Tes</button></form>
                                    @unless ($provider->is_active)
                                        <form method="POST" action="{{ route('superadmin.ai.activate', $provider) }}">@csrf<button type="submit" class="btn btn-sm btn-outline-success">Aktifkan</button></form>
                                    @endunless
                                    <form method="POST" action="{{ route('superadmin.ai.destroy', $provider) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus penyedia '.$provider->name.'?') }});">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            </summary>
                            <form method="POST" action="{{ route('superadmin.ai.update', $provider) }}" class="mt-3">
                                @csrf @method('PUT')
                                @include('superadmin.ai._fields', ['provider' => $provider])
                                <div class="d-flex justify-content-end mt-2"><button type="submit" class="btn btn-sm btn-primary">Simpan</button></div>
                            </form>
                        </details>
                    @empty
                        <div class="text-center text-muted py-3">Belum ada penyedia. Tambahkan di bawah, lalu klik Tes.</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold"><i class="ri-add-line"></i> Tambah Penyedia</h6></div>
                <div class="card-body p-3 p-md-4">
                    @if ($errors->any())
                        <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
                    @endif
                    <form method="POST" action="{{ route('superadmin.ai.store') }}">
                        @csrf
                        @include('superadmin.ai._fields', ['provider' => new \App\Models\AiProvider(['driver' => 'openai'])])
                        <div class="d-flex justify-content-end mt-2"><button type="submit" class="btn btn-primary">Tambah</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-primary-subtle">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-semibold mb-2"><i class="ri-lightbulb-line text-warning"></i> Cara pakai Classai Router</h6>
                    <ol class="small ps-3 mb-0 lh-lg">
                        <li>Ambil API key di <a href="https://router.classai.id/dashboard" target="_blank" rel="noopener noreferrer">dashboard Classai</a> (menu API Keys).</li>
                        <li>Di <b>Tambah Penyedia</b>: pilih <b>Classai Router</b>, tempel API key, klik <b>Tambah</b>.</li>
                        <li>Buka penyedia itu, klik <b>Lihat model yang tersedia</b>, pilih model, <b>Simpan</b>.</li>
                        <li>Klik <b>Tes</b>. Kalau hijau, klik <b>Aktifkan</b>. Selesai!</li>
                    </ol>
                    <div class="small text-muted mt-2">Tips: model "Haiku"/"Flash" = cepat & hemat, "Sonnet" = seimbang, "Opus" = paling pintar tapi lebih mahal.</div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-3 p-md-4">
                    <h6 class="fw-semibold mb-1"><i class="ri-chat-smile-3-line"></i> Asisten AI</h6>
                    <div class="text-muted small lh-sm mb-2">Ruang chat untuk superadmin, memakai penyedia yang aktif. Modelnya bisa dipilih di sana.</div>
                    <a href="{{ route('superadmin.ai-assistant.index') }}" class="btn btn-outline-primary w-100">Buka Asisten AI</a>
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Kuota Harian</h6>
                    <div class="text-muted small lh-sm">Batas "Buat dengan AI" per user per hari. Tim platform tidak dibatasi. 0 = AI dimatikan untuk user.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST" action="{{ route('superadmin.ai.quota') }}">
                        @csrf @method('PUT')
                        <div class="input-group mb-2">
                            <input type="number" name="daily_quota" min="0" max="1000" value="{{ old('daily_quota', $setting->daily_quota) }}" class="form-control">
                            <span class="input-group-text">kali / hari</span>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var request = function (url, options) {
            options = options || {};
            options.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
            return fetch(url, options).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok) throw new Error(data.message || (response.status === 429 ? 'Terlalu sering, tunggu sebentar.' : 'Gagal (kode ' + response.status + ').'));
                    return data;
                });
            });
        };
        var text = function (el, value, cls) { el.textContent = value; el.className = cls || ''; };

        // Saldo Classai (tidak ditagih). Ditulis dengan textContent: isi dari luar tidak pernah jadi HTML.
        document.querySelectorAll('[data-classai-balance]').forEach(function (box) {
            request(box.dataset.classaiBalance).then(function (data) {
                box.textContent = '';
                var main = document.createElement('span');
                var parts = [];
                if (data.label) parts.push(data.label);
                if (data.idr !== null && data.idr !== undefined && data.idr !== '') parts.push(typeof data.idr === 'number' ? 'Rp' + data.idr.toLocaleString('id-ID') : String(data.idr));
                if (!parts.length && data.credits !== null) parts.push(Number(data.credits).toLocaleString('id-ID') + ' kredit');
                text(main, '💰 Sisa saldo Classai: ' + (parts.join(' · ') || 'belum terbaca'), 'text-success fw-semibold');
                box.appendChild(main);
                (data.packages || []).forEach(function (p) {
                    var line = document.createElement('div');
                    text(line, 'Paket ' + p.name + (p.detail ? ' (' + p.detail + ')' : ''), 'text-muted');
                    box.appendChild(line);
                });
            }).catch(function (error) { box.textContent = ''; var s = document.createElement('span'); text(s, 'Saldo belum bisa dibaca: ' + error.message, 'text-danger'); box.appendChild(s); });
        });

        document.querySelectorAll('[data-classai-tools]').forEach(function (tools) {
            var input = document.querySelector('[data-model-input="' + tools.dataset.input + '"]');
            var list = document.getElementById(tools.dataset.list);
            var status = tools.parentNode.querySelector('[data-classai-status]');
            var results = tools.parentNode.querySelector('[data-speed-results]');

            tools.querySelector('[data-load-models]').addEventListener('click', function () {
                text(status, 'Mengambil daftar model dari Classai…', 'small text-muted mt-1');
                request(tools.dataset.modelsUrl + '?fresh=1').then(function (data) {
                    list.textContent = '';
                    data.models.forEach(function (m) {
                        var option = document.createElement('option');
                        option.value = m.id;
                        option.label = m.name + (m.context ? ' · konteks ' + m.context : '');
                        list.appendChild(option);
                    });
                    text(status, '✅ Ada ' + data.models.length + ' model. Klik kolom Model, pilih salah satu, lalu tekan Simpan.', 'small text-success mt-1');
                    input.focus();
                }).catch(function (error) { text(status, error.message, 'small text-danger mt-1'); });
            });

            tools.querySelector('[data-speed]').addEventListener('click', function () {
                var model = input.value.trim();
                if (!model) return;
                text(status, '⏱️ Sedang mengecek ' + model + '…', 'small text-muted mt-1');
                request(tools.dataset.speedUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ model: model }) }).then(function (data) {
                    var item = document.createElement('li');
                    var sec = data.ms / 1000;
                    text(item, (sec < 3 ? '🟢 Cepat' : sec < 8 ? '🟡 Sedang' : '🔴 Lambat') + ' · ' + data.model + ': ' + sec.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' detik');
                    results.prepend(item);
                    text(status, 'Selesai. Ganti isi kolom Model lalu cek lagi untuk membandingkan.', 'small text-success mt-1');
                }).catch(function (error) { text(status, error.message, 'small text-danger mt-1'); });
            });
        });
    })();
</script>
@endpush
