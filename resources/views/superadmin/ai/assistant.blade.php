@extends('layouts.dashboard')

@section('title', 'Asisten AI')
@section('page-section', 'Tools')

@push('styles')
<style>
    .ai-chat { height: calc(100vh - 380px); min-height: 520px; }
    .ai-thread { overflow-y: auto; }
    .ai-thread-inner { max-width: 860px; margin: 0 auto; }
    .ai-bubble { max-width: 85%; word-wrap: break-word; line-height: 1.65; }
    .ai-bubble pre { background: #f4f5f7; padding: .6rem .75rem; border-radius: .5rem; overflow-x: auto; white-space: pre; font-size: .85em; }
    .ai-bubble code { background: #f4f5f7; padding: 0 .25rem; border-radius: .2rem; }
    .ai-bubble pre code { background: none; padding: 0; }
    .ai-user { background: var(--bs-primary-bg-subtle); color: var(--bs-body-color); border-radius: 1.1rem 1.1rem .3rem 1.1rem !important; }
    .ai-assistant { max-width: 100%; flex: 1; padding-left: 0 !important; }
    .ai-avatar { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; background: var(--bs-primary-bg-subtle); color: var(--bs-primary); display: flex; align-items: center; justify-content: center; font-size: 15px; margin-top: 2px; }
    .ai-failed { background: var(--bs-danger-bg-subtle); padding: .5rem .75rem !important; border-radius: .75rem; }
    .ai-list a.active { background: var(--bs-primary-bg-subtle); color: var(--bs-body-color); border-color: transparent; }
    .ai-typing:empty::before { content: 'Sedang berpikir'; color: var(--bs-secondary-color); font-style: italic; }
    .ai-typing::after { content: ' ▍'; animation: ai-blink 1s steps(1) infinite; }
    @keyframes ai-blink { 50% { opacity: 0; } }
    .ai-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .5rem; }
    .ai-media-grid img { width: 100%; border-radius: .75rem; cursor: zoom-in; background: #eee; }
    .ai-bubble video { max-width: 100%; max-height: 440px; border-radius: .75rem; background: #000; }
    .ai-bubble audio { width: 340px; max-width: 100%; }
    .ai-composer { max-width: 860px; margin: 0 auto; background: #fff; transition: box-shadow .15s; }
    .ai-composer:focus-within { box-shadow: 0 .25rem 1rem rgba(0,0,0,.08) !important; }
    .ai-composer textarea { resize: none; min-height: 52px; max-height: 220px; font-size: .95rem; }
    .ai-composer textarea:focus { box-shadow: none; }
    .ai-mode { border-radius: 2rem !important; font-size: .8rem; padding: .2rem .65rem; }
    .ai-send { width: 36px; height: 36px; border-radius: 50% !important; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .ai-controls { height: 170px; overflow-y: auto; }
    .ai-panel .form-select, .ai-panel .form-control { font-size: .8rem; }
    .ai-panel label { font-size: .75rem; color: var(--bs-secondary-color); margin-bottom: 0; }
</style>
@endpush

@section('content')
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card mb-0">
                <div class="card-body p-2">
                    <a href="{{ route('superadmin.ai-assistant.index') }}" class="btn btn-primary w-100 mb-2"><i class="ri-add-line"></i> Mulai chat baru</a>
                    <div class="small text-muted px-1 mb-1">Riwayat percakapan</div>
                    <div class="ai-list list-group list-group-flush small" id="ai-list" style="max-height: calc(100vh - 340px); overflow-y: auto;">
                        @forelse ($conversations as $item)
                            <a href="{{ route('superadmin.ai-assistant.show', $item) }}" class="list-group-item list-group-item-action border-0 rounded px-2 py-2 {{ $current?->id === $item->id ? 'active fw-semibold' : '' }}">
                                <div class="text-truncate">{{ $item->title }}</div>
                                <div class="text-muted" style="font-size: .75em">{{ $item->updated_at->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div class="text-muted text-center py-3" data-empty>Belum ada percakapan.<br>Riwayat chat Anda akan muncul di sini.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            @if ($provider)
                {{-- Jenis & pengaturan: tinggi tetap, bisa di-scroll, supaya room chat di bawahnya tetap bersih. --}}
                <div class="card mb-3">
                    <div class="card-body p-3 ai-controls">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex flex-wrap gap-1" role="group" aria-label="Jenis hasil" id="ai-modes">
                            <button type="button" class="btn btn-sm btn-primary ai-mode" data-mode="text">💬 Teks</button>
                            <button type="button" class="btn btn-sm btn-light ai-mode" data-mode="image" @disabled(! $mediaEnabled) title="{{ $mediaEnabled ? 'Buat gambar' : 'Hanya tersedia dengan Classai Router' }}">🖼️ Gambar</button>
                            <button type="button" class="btn btn-sm btn-light ai-mode" data-mode="video" @disabled(! $mediaEnabled) title="{{ $mediaEnabled ? 'Buat video pendek' : 'Hanya tersedia dengan Classai Router' }}">🎬 Video</button>
                            <button type="button" class="btn btn-sm btn-light ai-mode" data-mode="audio" @disabled(! $mediaEnabled) title="{{ $mediaEnabled ? 'Ubah teks jadi suara' : 'Hanya tersedia dengan Classai Router' }}">🔊 Suara</button>
                        </div>
                            <span class="small text-muted"><i class="ri-shield-check-line text-success"></i> Tidak ada yang terkirim atau berubah di bizbos tanpa Anda.</span>
                        </div>
                        <div class="small text-muted mb-2" id="ai-mode-hint">Pilih “otak” AI yang menjawab. Model kecil = cepat &amp; hemat, model besar = lebih pintar.</div>
                    {{-- Pilihan per jenis --}}
                    <div class="ai-panel row g-2" data-panel="text">
                        <div class="col-auto">
                            <label for="ai-model" title="Model = 'otak' AI yang menjawab. Model besar (Opus, GPT-6) lebih pintar tapi lebih lama dan lebih mahal. Model kecil (Haiku, Flash) cepat dan hemat."><i class="ri-brain-line"></i> Otak AI <i class="ri-question-line"></i></label>
                            <select id="ai-model" class="form-select form-select-sm" style="max-width: 280px" @disabled(! $provider)>
                                @foreach ($models as $m)
                                    <option value="{{ $m['id'] }}" @selected($m['id'] === $model)>{{ $m['name'] }}{{ $m['name'] !== $m['id'] ? ' ('.$m['id'].')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ai-panel row g-2" data-panel="image" hidden>
                        <div class="col-12 col-md-5 col-xl-4"><label for="img-model">Model gambar</label><select id="img-model" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-3"><label for="img-aspect">Ukuran</label><select id="img-aspect" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-2"><label for="img-res">Resolusi</label><select id="img-res" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-2"><label for="img-n">Jumlah</label><select id="img-n" class="form-select form-select-sm"><option>1</option><option>2</option><option>3</option><option>4</option></select></div>
                        <div class="col-6 col-md-3 col-xl-2" data-quality><label for="img-quality">Mutu</label><select id="img-quality" class="form-select form-select-sm"></select></div>
                        <div class="col-12 col-md-6" data-reference><label for="img-ref" data-reference-label>Gambar contoh (opsional, untuk diedit / dijadikan acuan)</label><input type="file" id="img-ref" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm"></div>
                    </div>
                    <div class="ai-panel row g-2" data-panel="video" hidden>
                        <div class="col-12 col-md-4"><label for="vid-model">Model video</label><select id="vid-model" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-3"><label for="vid-aspect">Ukuran</label><select id="vid-aspect" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-2"><label for="vid-res">Resolusi</label><select id="vid-res" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-3 col-xl-2"><label for="vid-sec">Durasi</label><select id="vid-sec" class="form-select form-select-sm"></select></div>
                        <div class="col-12 col-md-6"><label for="vid-ref">Gambar awal (opsional, jadi frame pertama video)</label><input type="file" id="vid-ref" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm"></div>
                    </div>
                    <div class="ai-panel row g-2" data-panel="audio" hidden>
                        <div class="col-12 col-md-3"><label for="aud-model">Mesin suara</label><select id="aud-model" class="form-select form-select-sm"></select></div>
                        <div class="col-9 col-md-4"><label for="aud-voice">Suara</label><select id="aud-voice" class="form-select form-select-sm"><option value="">Memuat…</option></select></div>
                        <div class="col-3 col-md-1 d-flex align-items-end" style="min-width: 56px"><button type="button" class="btn btn-sm btn-outline-secondary w-100" id="aud-sample" title="Dengar contoh suara" disabled><i class="ri-play-line"></i></button></div>
                        <div class="col-6 col-md-2"><label for="aud-style">Gaya</label><select id="aud-style" class="form-select form-select-sm"></select></div>
                        <div class="col-6 col-md-2"><label for="aud-speed">Kecepatan <span id="aud-speed-val">1,0×</span></label><input type="range" id="aud-speed" min="0.7" max="1.2" step="0.05" value="1" class="form-range"></div>
                    </div>
                    <div class="small mt-2" id="ai-panel-info"></div>
                    </div>
                </div>
            @endif
            <div class="card mb-0 ai-chat d-flex flex-column">
                <div class="card-header bg-white border-bottom px-3 py-2 d-flex flex-wrap align-items-center gap-2">
                    @if ($current)
                        <form method="POST" action="{{ route('superadmin.ai-assistant.rename', $current) }}" class="d-flex gap-1 flex-grow-1" style="min-width: 200px">
                            @csrf @method('PUT')
                            <input type="text" name="title" value="{{ $current->title }}" maxlength="150" class="form-control form-control-sm border-0 fw-semibold px-1" aria-label="Judul percakapan" title="Klik untuk mengganti judul">
                            <button type="submit" class="btn btn-sm btn-light" title="Simpan judul"><i class="ri-check-line"></i></button>
                        </form>
                        <form method="POST" action="{{ route('superadmin.ai-assistant.destroy', $current) }}" onsubmit="return confirm('Hapus percakapan ini beserta gambar, video & suaranya? Tidak bisa dikembalikan.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus percakapan"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    @else
                        <div class="fw-semibold flex-grow-1"><i class="ri-chat-new-line text-primary"></i> Percakapan baru</div>
                    @endif
                </div>

                <div class="card-body ai-thread flex-grow-1 p-3 p-md-4" id="ai-thread">
                    <div class="ai-thread-inner" id="ai-thread-inner">
                    @unless ($provider)
                        <div class="alert alert-warning mb-0">
                            <div class="fw-semibold mb-1">AI belum disambungkan</div>
                            Tambahkan dan aktifkan penyedia AI dulu (misalnya Classai Router) di <a href="{{ route('superadmin.ai.index') }}">Settings › AI</a>. Setelah itu halaman ini langsung bisa dipakai.
                        </div>
                    @endunless
                    <div class="h-100" id="ai-welcome" @if ($messages->isNotEmpty() || ! $provider) hidden @endif>
                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted py-5">
                            <i class="ri-chat-smile-3-line fs-1 mb-2"></i>
                            <div class="fw-semibold">👋 Halo, {{ \Illuminate\Support\Str::before(auth()->user()->name.' ', ' ') }}! Mau dibantu apa hari ini?</div>
                            <div class="small">Pilih jenisnya di atas (Teks, Gambar, Video, Suara), lalu ketik pesan di bawah.</div>
                        </div>
                    </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-0 px-3 pb-3 pt-1">
                    <form id="ai-form" autocomplete="off">
                        <div class="ai-composer border rounded-4 shadow-sm d-flex align-items-end gap-2 pe-2 pb-2">
                            <textarea id="ai-input" rows="1" maxlength="8000" class="form-control border-0 px-3 pt-3 flex-grow-1" placeholder="Tulis pesan untuk Asisten AI…" @disabled(! $provider)></textarea>
                            <button type="button" class="btn btn-sm btn-outline-secondary ai-send flex-shrink-0" id="ai-stop" title="Hentikan jawaban" hidden><i class="ri-stop-fill"></i></button>
                            <button type="submit" class="btn btn-primary ai-send flex-shrink-0" id="ai-send" title="Kirim (Enter)" @disabled(! $provider)><i class="ri-arrow-up-line fs-5"></i><span class="visually-hidden" id="ai-send-label">Kirim</span></button>
                        </div>
                        <div class="ai-composer d-flex flex-wrap justify-content-between gap-2 small text-muted mt-1 px-2" style="background: none">
                            <span id="ai-status" class="ms-auto">
                                @if ($provider)
                                    Sisa hari ini: {{ max(0, \App\Http\Controllers\Superadmin\Ai\AssistantController::DAILY_LIMIT - $usedToday) }} dari {{ \App\Http\Controllers\Superadmin\Ai\AssistantController::DAILY_LIMIT }} pesan
                                @endif
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var $ = function (id) { return document.getElementById(id); };
        var thread = $('ai-thread'), threadInner = $('ai-thread-inner'), form = $('ai-form'), input = $('ai-input'), modelSelect = $('ai-model');
        var sendButton = $('ai-send'), sendLabel = $('ai-send-label'), stopButton = $('ai-stop'), status = $('ai-status');
        var welcome = $('ai-welcome'), info = $('ai-panel-info'), hint = $('ai-mode-hint');
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var urls = {{ \Illuminate\Support\Js::from([
            'send' => route('superadmin.ai-assistant.send'),
            'generate' => route('superadmin.ai-assistant.media.generate'),
            'options' => route('superadmin.ai-assistant.media.options'),
            'voices' => route('superadmin.ai-assistant.media.voices'),
            'status' => route('superadmin.ai-assistant.media.status', ['message' => '__ID__']),
            'monitor' => \Illuminate\Support\Facades\Route::has('superadmin.monitor.index') ? route('superadmin.monitor.index') : null,
        ]) }};
        var conversationId = {{ \Illuminate\Support\Js::from($current?->id) }};
        var initial = {{ \Illuminate\Support\Js::from($messages->values()) }};
        var textStatus = status.textContent.trim();
        var controller = null, mode = 'text', catalog = null, voices = {};
        var LABEL = { text: 'Teks', image: 'Gambar', video: 'Video', audio: 'Suara' };
        var ICON = { text: '💬', image: '🖼️', video: '🎬', audio: '🔊' };

        // ---------- Format teks: selalu di-escape dulu, baru diberi format sederhana (tidak ada HTML dari AI yang dijalankan) ----------
        var escapeHtml = function (s) {
            return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
        };
        var inline = function (s) {
            return s
                .replace(/`([^`\n]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="_blank" rel="noopener noreferrer nofollow">$2</a>');
        };
        var render = function (src) {
            return String(src).split('```').map(function (part, i) {
                if (i % 2 === 1) return '<pre><code>' + escapeHtml(part.replace(/^[^\n]*\n/, '')) + '</code></pre>';
                return escapeHtml(part).split('\n').map(function (line) {
                    var heading = line.match(/^#{1,4}\s+(.*)$/);
                    return heading ? '<strong>' + inline(heading[1]) + '</strong>' : inline(line);
                }).join('<br>');
            }).join('');
        };
        var rupiah = function (n) { return 'Rp' + Math.round(n).toLocaleString('id-ID'); };
        var seconds = function (ms) { return (ms / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 }); };
        var meta = function (m) {
            var parts = [];
            if (m.model) parts.push(m.model);
            (m.details || []).forEach(function (d) { parts.push(d); });
            if (m.duration_ms) parts.push((m.kind === 'video' ? 'selesai dalam ' : 'dijawab dalam ') + seconds(m.duration_ms) + ' detik');
            if (m.input_tokens || m.output_tokens) parts.push(((m.input_tokens || 0) + (m.output_tokens || 0)).toLocaleString('id-ID') + ' token');
            if (m.cost_rp) parts.push('biaya ±' + rupiah(m.cost_rp));
            if (m.status === 'stopped') parts.push('dihentikan');
            return parts.join(' · ');
        };
        var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; };

        // ---------- Gelembung chat ----------
        var copyButton = function (box) {
            var copy = el('button', 'btn btn-link btn-sm p-0 text-muted');
            copy.type = 'button';
            copy.innerHTML = '<i class="ri-file-copy-line"></i> Salin';
            copy.addEventListener('click', function () {
                var done = function () { copy.innerHTML = '<i class="ri-check-line"></i> Tersalin'; setTimeout(function () { copy.innerHTML = '<i class="ri-file-copy-line"></i> Salin'; }, 1500); };
                if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(box.dataset.raw || '').then(done); return; }
                var temp = document.createElement('textarea'); temp.value = box.dataset.raw || '';
                document.body.appendChild(temp); temp.select(); document.execCommand('copy'); temp.remove(); done();
            });
            return copy;
        };
        var downloadLink = function (file, label) {
            var a = el('a', 'btn btn-sm btn-outline-secondary py-0');
            a.href = file.download;
            a.innerHTML = '<i class="ri-download-2-line"></i> ';
            a.appendChild(document.createTextNode(label));
            return a;
        };
        var waitingText = function (m) {
            var queued = m._queued || 0;
            if (m.kind === 'video') return m.status === 'pending' ? '🎬 Menyiapkan video…' : '🎬 Video sedang dibuat. Biasanya 1–10 menit. Boleh tinggal, hasilnya muncul sendiri di sini.';
            var base = m.kind === 'image' ? '🖼️ Gambar sedang dibuat…' : '🔊 Suara sedang dibuat…';
            if (m.status === 'pending' && queued >= 2) {
                return base + ' Masih menunggu antrean. Pastikan antrean (queue worker) berjalan' + (urls.monitor ? ', cek di Settings › Antrean & Tugas.' : '.');
            }
            return base;
        };

        var fillAssistant = function (view, m) {
            var body = view.body;
            body.textContent = '';
            body.classList.remove('ai-typing', 'ai-failed');
            view.actions.textContent = '';

            if (m.status === 'failed') {
                body.classList.add('ai-failed');
                body.textContent = '⚠️ ' + (m.content || 'Gagal dibuat.');
            } else if (m.status === 'pending' || m.status === 'processing') {
                var wait = el('div', 'text-muted fst-italic');
                wait.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>';
                wait.appendChild(document.createTextNode(waitingText(m)));
                body.appendChild(wait);
            } else if (m.kind === 'image') {
                var grid = el('div', 'ai-media-grid mb-1');
                (m.media || []).forEach(function (file, i) {
                    var link = el('a'); link.href = file.url; link.target = '_blank'; link.rel = 'noopener';
                    var img = el('img'); img.src = file.url; img.alt = 'Gambar hasil AI ' + (i + 1); img.loading = 'lazy';
                    link.appendChild(img); grid.appendChild(link);
                    view.actions.appendChild(downloadLink(file, (m.media.length > 1 ? 'Unduh ' + (i + 1) : 'Unduh gambar')));
                });
                body.appendChild(grid);
            } else if (m.kind === 'video') {
                (m.media || []).forEach(function (file) {
                    var video = el('video'); video.src = file.url; video.controls = true; video.preload = 'metadata'; video.playsInline = true;
                    body.appendChild(video);
                    view.actions.appendChild(downloadLink(file, 'Unduh video'));
                });
            } else if (m.kind === 'audio') {
                (m.media || []).forEach(function (file) {
                    var audio = el('audio'); audio.src = file.url; audio.controls = true; audio.preload = 'metadata';
                    body.appendChild(audio);
                    view.actions.appendChild(downloadLink(file, 'Unduh suara'));
                });
                body.appendChild(el('div', 'small text-muted mt-1', 'ℹ️ Saat dipakai, beri tahu pendengar bahwa suara ini dibuat dengan AI.'));
            } else {
                body.innerHTML = render(m.content || '');
                view.box.dataset.raw = m.content || '';
                view.actions.appendChild(copyButton(view.box));
            }
            view.meta.textContent = (m.status === 'done' || m.status === 'stopped') ? meta(m) : '';
        };

        var bubble = function (m) {
            welcome.hidden = true;
            var isUser = m.role === 'user';
            var row = el('div', 'd-flex gap-2 mb-4 ' + (isUser ? 'justify-content-end' : 'justify-content-start'));
            if (!isUser) row.appendChild(el('div', 'ai-avatar', '✨'));
            var box = el('div', isUser ? 'ai-bubble ai-user px-3 py-2' : 'ai-bubble ai-assistant');
            var body = el('div');
            var foot = el('div', 'd-flex flex-wrap align-items-center gap-2 mt-1 small text-muted');
            var metaText = el('span');
            var actions = el('span', 'd-flex flex-wrap gap-1');
            foot.appendChild(metaText); foot.appendChild(actions);
            box.appendChild(body); box.appendChild(foot); row.appendChild(box); threadInner.appendChild(row);
            var view = { box: box, body: body, meta: metaText, actions: actions };

            if (isUser) {
                if (m.kind && m.kind !== 'text') {
                    body.appendChild(el('div', 'small text-muted mb-1', ICON[m.kind] + ' ' + LABEL[m.kind] + (m.details && m.details.length ? ' · ' + m.details.join(' · ') : '')));
                    body.appendChild(el('div', '', m.content));
                } else {
                    body.innerHTML = render(m.content || '');
                }
            } else {
                fillAssistant(view, m);
            }
            thread.scrollTop = thread.scrollHeight;
            return view;
        };

        // ---------- Cek status media (gambar/suara tiap 4 detik, video tiap 10 detik) ----------
        var watch = function (view, m) {
            var every = m.kind === 'video' ? 10000 : 4000;
            var tick = function () {
                fetch(urls.status.replace('__ID__', m.id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                    .then(function (data) {
                        data.message._queued = data.queued_minutes;
                        fillAssistant(view, data.message);
                        if (data.message.status === 'pending' || data.message.status === 'processing') { setTimeout(tick, every); return; }
                        thread.scrollTop = thread.scrollHeight;
                    })
                    .catch(function () { setTimeout(tick, every * 2); });
            };
            setTimeout(tick, every);
        };

        initial.forEach(function (m) {
            var view = bubble(m);
            if (m.role === 'assistant' && (m.status === 'pending' || m.status === 'processing')) watch(view, m);
        });

        // ---------- Pilihan mode & isiannya ----------
        var fillSelect = function (select, items, selected) {
            select.textContent = '';
            items.forEach(function (item) {
                var option = el('option', '', item[1]); option.value = item[0];
                if (String(item[0]) === String(selected)) option.selected = true;
                select.appendChild(option);
            });
        };
        var current = function (kind, id) { return (catalog[kind] || []).find(function (m) { return m.id === id; }) || catalog[kind][0]; };

        var setupImage = function () {
            var m = current('image', $('img-model').value);
            fillSelect($('img-aspect'), m.aspects.map(function (a) { return [a, catalog.image_aspects[a] || a]; }), $('img-aspect').value || '4:5');
            fillSelect($('img-res'), m.resolutions.map(function (r) { return [r, r]; }), $('img-res').value);
            document.querySelector('[data-quality]').hidden = !m.quality;
            document.querySelector('[data-reference]').hidden = m.reference === 'none';
            document.querySelector('[data-reference-label]').textContent = m.reference === 'required'
                ? 'Gambar yang mau diedit (wajib untuk model ini)'
                : 'Gambar contoh (opsional, untuk diedit / dijadikan acuan)';
            updateInfo();
        };
        var setupVideo = function () {
            var m = current('video', $('vid-model').value);
            fillSelect($('vid-aspect'), m.aspects.map(function (a) { return [a, catalog.video_aspects[a] || a]; }), $('vid-aspect').value || '9:16');
            fillSelect($('vid-res'), m.resolutions.map(function (r) { return [r, r]; }), $('vid-res').value);
            var list = m.seconds.list || [];
            if (!list.length) for (var s = m.seconds.min; s <= m.seconds.max; s++) list.push(s);
            fillSelect($('vid-sec'), list.map(function (s) { return [s, s + ' detik']; }), $('vid-sec').value || (list.indexOf(8) >= 0 ? 8 : list[0]));
            updateInfo();
        };
        var loadVoices = function () {
            var model = $('aud-model').value, select = $('aud-voice');
            var apply = function (list) {
                fillSelect(select, list.length ? list.map(function (v) { return [v.id, v.name]; }) : [['', 'Tidak ada suara untuk mesin ini']], select.dataset.last);
                updateSample();
            };
            if (voices[model]) { apply(voices[model]); return; }
            fillSelect(select, [['', 'Memuat daftar suara…']]);
            fetch(urls.voices + '?model=' + encodeURIComponent(model), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Gagal memuat suara.'); return d; }); })
                .then(function (d) { voices[model] = d.voices || []; apply(voices[model]); })
                .catch(function (e) { fillSelect(select, [['', e.message]]); });
        };
        var updateSample = function () {
            var v = (voices[$('aud-model').value] || []).find(function (x) { return x.id === $('aud-voice').value; });
            $('aud-sample').disabled = !(v && v.sample);
            $('aud-voice').dataset.last = $('aud-voice').value;
            updateInfo();
        };
        var sampleAudio = null;
        $('aud-sample').addEventListener('click', function () {
            var v = (voices[$('aud-model').value] || []).find(function (x) { return x.id === $('aud-voice').value; });
            if (!v || !v.sample) return;
            if (sampleAudio) sampleAudio.pause();
            sampleAudio = new Audio(v.sample);
            sampleAudio.play().catch(function () {});
        });

        var autosize = function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 220) + 'px'; };
        input.addEventListener('input', autosize);

        var updateInfo = function () {
            if (mode === 'text' || !catalog) { info.textContent = ''; status.textContent = textStatus; return; }
            var used = catalog.used[mode] || { used: 0, limit: 0 };
            status.textContent = 'Sisa ' + LABEL[mode].toLowerCase() + ' hari ini: ' + Math.max(0, used.limit - used.used) + ' dari ' + used.limit;
            var text = '';
            if (mode === 'image') {
                var im = current('image', $('img-model').value);
                text = '💰 Perkiraan ' + (im.price || '-') + ' per gambar' + ($('img-n').value > 1 ? ' (× ' + $('img-n').value + ')' : '') + (im.time ? ' · ⏱️ ' + im.time : '') + ' · Gagal = tidak ditagih.';
            } else if (mode === 'video') {
                var vm = current('video', $('vid-model').value), per = vm.price_per_second[$('vid-res').value];
                var est = per ? Math.max(5000, per * Number($('vid-sec').value)) : null;
                text = '💰 ' + (est ? 'Perkiraan ±' + rupiah(est) + ' untuk video ini' : 'Harga per video lihat katalog Classai') + (vm.time ? ' · ⏱️ ' + vm.time : '') + ' · Kredit baru dipotong kalau video berhasil.';
            } else if (mode === 'audio') {
                var am = current('audio', $('aud-model').value), chars = input.value.length;
                text = '💰 ' + (am.price || '-') + ' (±1.000 karakter ≈ 1 menit) · ' + chars.toLocaleString('id-ID') + '/' + am.max_chars.toLocaleString('id-ID') + ' karakter · Beri tahu pendengar bahwa suara dibuat AI.';
            }
            info.textContent = text;
            info.className = 'small text-muted';
        };

        var loadCatalog = function () {
            if (catalog) return Promise.resolve(catalog);
            info.textContent = '⏳ Memuat pilihan model dari Classai…'; info.className = 'small text-muted';
            return fetch(urls.options, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Gagal memuat pilihan.'); return d; }); })
                .then(function (d) {
                    catalog = d;
                    fillSelect($('img-model'), d.image.map(function (m) { return [m.id, m.name + (m.price ? ' · ' + m.price : '')]; }), 'flux-2-klein');
                    fillSelect($('img-quality'), Object.keys(d.qualities).map(function (k) { return [k, d.qualities[k]]; }), 'medium');
                    fillSelect($('vid-model'), d.video.map(function (m) { return [m.id, m.name]; }), 'seedance-2.0-mini');
                    fillSelect($('aud-model'), d.audio.map(function (m) { return [m.id, m.name + (m.price ? ' · ' + m.price : '')]; }), 'voxcpm2');
                    fillSelect($('aud-style'), Object.keys(d.styles).map(function (k) { return [k, d.styles[k]]; }), 'iklan');
                    setupImage(); setupVideo(); loadVoices();
                    return d;
                });
        };

        var HINTS = {
            text: ['Pilih “otak” AI yang menjawab. Model kecil = cepat & hemat, model besar = lebih pintar.', 'Tulis pesan untuk Asisten AI…', 'Kirim'],
            image: ['Ceritakan gambar yang Anda mau: siapa/apa, suasana, warna, gaya foto. Tulisan panjang di dalam gambar sering salah eja, lebih baik ditambah sendiri di Canva.', 'Ceritakan gambar yang Anda mau…', 'Buat gambar'],
            video: ['Ceritakan adegan, gerakan kamera, dan suara yang Anda mau. Video dikerjakan di latar belakang: boleh tinggal, hasilnya muncul sendiri.', 'Ceritakan adegan video yang Anda mau…', 'Buat video'],
            audio: ['Tulis teks yang mau dibacakan (bukan instruksi). Angka, Rupiah & singkatan dibaca otomatis dengan ejaan Indonesia.', 'Tempel teks yang mau dibacakan…', 'Buat suara'],
        };
        var setMode = function (next) {
            var go = function () {
                mode = next;
                document.querySelectorAll('#ai-modes [data-mode]').forEach(function (b) {
                    b.classList.toggle('btn-primary', b.dataset.mode === next);
                    b.classList.toggle('btn-light', b.dataset.mode !== next);
                });
                document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== next; });
                hint.textContent = HINTS[next][0];
                input.placeholder = HINTS[next][1];
                sendLabel.textContent = HINTS[next][2];
                updateInfo();
            };
            if (next === 'text' || catalog) { go(); return Promise.resolve(); }
            // Tombol & petunjuk langsung pindah; pilihan model menyusul setelah dimuat.
            mode = next;
            document.querySelectorAll('#ai-modes [data-mode]').forEach(function (b) {
                b.classList.toggle('btn-primary', b.dataset.mode === next);
                b.classList.toggle('btn-light', b.dataset.mode !== next);
            });
            document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = true; });
            hint.textContent = HINTS[next][0];
            input.placeholder = HINTS[next][1];
            sendLabel.textContent = HINTS[next][2];
            sendButton.disabled = true;
            return loadCatalog().then(function () { sendButton.disabled = false; if (mode === next) go(); })
                .catch(function (e) { sendButton.disabled = false; info.textContent = '⚠️ ' + e.message; info.className = 'small text-danger'; });
        };
        document.querySelectorAll('#ai-modes [data-mode]').forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.mode); }); });
        $('img-model').addEventListener('change', setupImage);
        $('img-n').addEventListener('change', updateInfo);
        $('vid-model').addEventListener('change', setupVideo);
        ['vid-res', 'vid-sec'].forEach(function (id) { $(id).addEventListener('change', updateInfo); });
        $('aud-model').addEventListener('change', loadVoices);
        $('aud-voice').addEventListener('change', updateSample);
        $('aud-speed').addEventListener('input', function () { $('aud-speed-val').textContent = Number(this.value).toLocaleString('id-ID', { minimumFractionDigits: 1 }) + '×'; });
        input.addEventListener('input', function () { if (mode === 'audio') updateInfo(); });

        // ---------- Kirim ----------
        var busy = function (on) {
            sendButton.disabled = on; input.disabled = on; modelSelect.disabled = on;
            document.querySelectorAll('#ai-modes [data-mode]').forEach(function (b) { b.disabled = on || (b.dataset.mode !== 'text' && {{ $mediaEnabled ? 'false' : 'true' }}); });
            stopButton.hidden = !(on && mode === 'text');
        };
        var addToList = function (conv) {
            var list = $('ai-list'), empty = list.querySelector('[data-empty]');
            if (empty) empty.remove();
            list.querySelectorAll('a.active').forEach(function (a) { a.classList.remove('active', 'fw-semibold'); });
            var link = el('a', 'list-group-item list-group-item-action border-0 rounded px-2 py-2 active fw-semibold');
            link.href = conv.url;
            link.appendChild(el('div', 'text-truncate', conv.title));
            list.prepend(link);
        };
        var adopt = function (conv) {
            if (conversationId) return;
            conversationId = conv.id;
            history.replaceState(null, '', conv.url);
            addToList(conv);
        };
        var errorFrom = function (response) {
            return response.json().catch(function () { return {}; }).then(function (data) {
                var message = data.message;
                if (data.errors) message = Object.values(data.errors)[0][0];
                if (response.status === 419) message = 'Sesi Anda sudah habis. Muat ulang halaman ini, lalu kirim lagi.';
                if (response.status === 413) message = 'File terlalu besar. Maksimal 10 MB.';
                throw new Error(message || 'Gagal terkirim (kode ' + response.status + '). Coba lagi ya.');
            });
        };

        var sendText = function (text) {
            bubble({ role: 'user', kind: 'text', content: text });
            var answer = bubble({ role: 'assistant', kind: 'text', content: '', status: 'done' });
            answer.actions.textContent = '';
            answer.body.classList.add('ai-typing');
            var full = '', failed = null;
            busy(true);
            status.textContent = '🤔 AI sedang berpikir…';
            controller = new AbortController();

            fetch(urls.send, {
                method: 'POST', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream, application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message: text, conversation_id: conversationId, model: modelSelect.value })
            }).then(function (response) {
                if (!response.ok || (response.headers.get('Content-Type') || '').indexOf('text/event-stream') === -1) return errorFrom(response);
                var reader = response.body.getReader(), decoder = new TextDecoder(), buffer = '';
                var handle = function (event) {
                    if (event.t === 'start') { adopt({ id: event.conversation_id, title: event.title, url: event.url }); status.textContent = '✍️ AI sedang menulis jawaban…'; }
                    else if (event.t === 'delta') { full += event.text; answer.body.innerHTML = render(full); answer.box.dataset.raw = full; thread.scrollTop = thread.scrollHeight; }
                    else if (event.t === 'done') { event.kind = 'text'; answer.meta.textContent = meta(event); }
                    else if (event.t === 'error') { failed = event.message; }
                };
                var pump = function () {
                    return reader.read().then(function (chunk) {
                        if (chunk.done) return;
                        buffer += decoder.decode(chunk.value, { stream: true });
                        var parts = buffer.split('\n\n');
                        buffer = parts.pop();
                        parts.forEach(function (part) {
                            part.split('\n').forEach(function (line) {
                                if (line.indexOf('data: ') === 0) { try { handle(JSON.parse(line.slice(6))); } catch (e) {} }
                            });
                        });
                        return pump();
                    });
                };
                return pump();
            }).catch(function (error) {
                if (error.name !== 'AbortError') failed = error.message;
            }).then(function () {
                answer.body.classList.remove('ai-typing');
                if (failed) {
                    answer.body.classList.add('ai-failed');
                    answer.body.textContent = '⚠️ ' + failed;
                } else if (!full) {
                    answer.body.textContent = '(Jawaban dihentikan)';
                } else {
                    answer.actions.appendChild(copyButton(answer.box));
                }
                busy(false);
                textStatus = failed ? 'Ada kendala. Coba kirim lagi, atau pilih otak AI lain.' : '✅ Selesai. Silakan lanjut bertanya atau salin jawabannya.';
                status.textContent = textStatus;
                controller = null;
                input.focus();
            });
        };

        var sendMedia = function (text) {
            var data = new FormData();
            data.append('kind', mode);
            data.append('prompt', text);
            if (conversationId) data.append('conversation_id', conversationId);
            var file = null;
            if (mode === 'image') {
                var im = current('image', $('img-model').value);
                data.append('model', im.id); data.append('aspect_ratio', $('img-aspect').value);
                data.append('resolution', $('img-res').value); data.append('n', $('img-n').value);
                if (im.quality) data.append('quality', $('img-quality').value);
                if (im.reference !== 'none') file = $('img-ref').files[0];
                if (im.reference === 'required' && !file) { info.textContent = '⚠️ Model ini perlu 1 gambar untuk diedit. Lampirkan gambarnya dulu.'; info.className = 'small text-danger'; return; }
            } else if (mode === 'video') {
                data.append('model', $('vid-model').value); data.append('aspect_ratio', $('vid-aspect').value);
                data.append('resolution', $('vid-res').value); data.append('seconds', $('vid-sec').value);
                file = $('vid-ref').files[0];
            } else {
                if (!$('aud-voice').value) { info.textContent = '⚠️ Pilih suaranya dulu.'; info.className = 'small text-danger'; return; }
                data.append('model', $('aud-model').value); data.append('voice', $('aud-voice').value);
                data.append('voice_name', $('aud-voice').selectedOptions[0] ? $('aud-voice').selectedOptions[0].textContent : '');
                data.append('style', $('aud-style').value); data.append('speed', $('aud-speed').value);
            }
            if (file) {
                if (file.size > 10 * 1024 * 1024) { info.textContent = '⚠️ Gambar terlalu besar. Maksimal 10 MB.'; info.className = 'small text-danger'; return; }
                data.append('reference', file);
            }

            busy(true);
            status.textContent = '📤 Mengirim permintaan…';
            fetch(urls.generate, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.ok ? response.json() : errorFrom(response); })
                .then(function (res) {
                    adopt(res.conversation);
                    if (catalog.used[mode]) catalog.used[mode].used++;
                    updateInfo();
                    input.value = '';
                    autosize();
                    ['img-ref', 'vid-ref'].forEach(function (id) { $(id).value = ''; });
                    bubble(res.user);
                    watch(bubble(res.assistant), res.assistant);
                    status.textContent = '⏳ Sedang dibuat. Anda bisa lanjut chat atau membuat yang lain.';
                })
                .catch(function (error) { info.textContent = '⚠️ ' + error.message; info.className = 'small text-danger'; })
                .then(function () { busy(false); input.focus(); });
        };

        var send = function () {
            var text = input.value.trim();
            if (!text || sendButton.disabled) return;
            if (mode === 'text') { input.value = ''; autosize(); sendText(text); } else { sendMedia(text); }
        };

        form.addEventListener('submit', function (event) { event.preventDefault(); send(); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); send(); }
        });
        stopButton.addEventListener('click', function () { if (controller) controller.abort(); });
        thread.scrollTop = thread.scrollHeight;
        if (!input.disabled) input.focus();
    })();
</script>
@endpush
