@extends('layouts.dashboard')

@section('title', 'Asisten AI')
@section('page-section', 'Tools')

@push('styles')
<style>
    .ai-chat { height: calc(100vh - 260px); min-height: 460px; }
    .ai-thread { overflow-y: auto; }
    .ai-bubble { max-width: 85%; word-wrap: break-word; line-height: 1.55; }
    .ai-bubble pre { background: #f4f5f7; padding: .6rem .75rem; border-radius: .4rem; overflow-x: auto; white-space: pre; font-size: .85em; }
    .ai-bubble code { background: #f4f5f7; padding: 0 .25rem; border-radius: .2rem; }
    .ai-bubble pre code { background: none; padding: 0; }
    .ai-user { background: var(--bs-primary); color: #fff; }
    .ai-user code, .ai-user pre { background: rgba(255,255,255,.15); color: #fff; }
    .ai-list a.active { background: var(--bs-primary-bg-subtle); }
    .ai-typing:empty::before { content: 'AI sedang berpikir'; color: var(--bs-secondary-color); font-style: italic; }
    .ai-typing::after { content: ' ▍'; animation: ai-blink 1s steps(1) infinite; }
    @keyframes ai-blink { 50% { opacity: 0; } }
    .ai-example:hover { border-color: var(--bs-primary) !important; }
</style>
@endpush

@section('content')
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card mb-0">
                <div class="card-body p-2">
                    <a href="{{ route('superadmin.ai-assistant.index') }}" class="btn btn-primary w-100 mb-2"><i class="ri-add-line"></i> Mulai chat baru</a>
                    <div class="small text-muted px-1 mb-1">Riwayat percakapan</div>
                    <div class="ai-list list-group list-group-flush small" id="ai-list" style="max-height: calc(100vh - 360px); overflow-y: auto;">
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
            <div class="card mb-0 ai-chat d-flex flex-column">
                <div class="card-header bg-white border-bottom px-3 py-2 d-flex flex-wrap align-items-center gap-2">
                    @if ($current)
                        <form method="POST" action="{{ route('superadmin.ai-assistant.rename', $current) }}" class="d-flex gap-1 flex-grow-1" style="min-width: 200px">
                            @csrf @method('PUT')
                            <input type="text" name="title" value="{{ $current->title }}" maxlength="150" class="form-control form-control-sm border-0 fw-semibold px-1" aria-label="Judul percakapan" title="Klik untuk mengganti judul">
                            <button type="submit" class="btn btn-sm btn-light" title="Simpan judul"><i class="ri-check-line"></i></button>
                        </form>
                        <form method="POST" action="{{ route('superadmin.ai-assistant.destroy', $current) }}" onsubmit="return confirm('Hapus percakapan ini? Riwayatnya tidak bisa dikembalikan.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus percakapan"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    @else
                        <div class="fw-semibold flex-grow-1"><i class="ri-chat-new-line text-primary"></i> Percakapan baru</div>
                    @endif
                </div>

                <div class="card-body ai-thread flex-grow-1 p-3" id="ai-thread">
                    @unless ($provider)
                        <div class="alert alert-warning mb-0">
                            <div class="fw-semibold mb-1">AI belum disambungkan</div>
                            Tambahkan dan aktifkan penyedia AI dulu (misalnya Classai Router) di <a href="{{ route('superadmin.ai.index') }}">Settings › AI</a>. Setelah itu halaman ini langsung bisa dipakai.
                        </div>
                    @endunless
                    <div class="py-4 px-md-4" id="ai-welcome" @if ($messages->isNotEmpty() || ! $provider) hidden @endif>
                        <div class="text-center mb-4">
                            <div class="fs-1 mb-1">👋</div>
                            <h5 class="fw-semibold mb-1">Halo, {{ \Illuminate\Support\Str::before(auth()->user()->name.' ', ' ') }}! Mau dibantu apa hari ini?</h5>
                            <div class="text-muted small">Ketik seperti sedang ngobrol dengan rekan kerja. Atau klik salah satu contoh di bawah, sesuaikan, lalu kirim.</div>
                        </div>
                        <div class="row g-2">
                            @foreach ([
                                ['ri-instagram-line', 'Ide konten', 'Buatkan 5 ide konten Instagram minggu ini untuk menarik pemilik bimbel dan kursus memakai bizbos. Sertakan caption singkat dan hashtag.'],
                                ['ri-whatsapp-line', 'Pesan WhatsApp', 'Tulis pesan WhatsApp yang ramah untuk pengguna trial bizbos yang belum upgrade. Maksimal 5 kalimat, ajak mereka bertanya kalau ada kendala.'],
                                ['ri-mail-send-line', 'Email penawaran', 'Buatkan email perkenalan singkat bizbos untuk pemilik studio senam/yoga. Fokus pada jadwal kelas dan tagihan otomatis.'],
                                ['ri-calendar-check-line', 'Rencana marketing', 'Susun rencana marketing harian bizbos untuk 1 minggu ke depan. Tiap hari 2-3 tugas yang bisa saya kerjakan dalam 30 menit.'],
                            ] as [$icon, $label, $prompt])
                                <div class="col-sm-6">
                                    <button type="button" class="btn btn-light border w-100 text-start p-3 h-100 ai-example" data-example="{{ $prompt }}">
                                        <div class="fw-semibold mb-1"><i class="{{ $icon }} text-primary me-1"></i> {{ $label }}</div>
                                        <div class="small text-muted lh-sm">{{ \Illuminate\Support\Str::limit($prompt, 95) }}</div>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <div class="small text-muted text-center mt-3"><i class="ri-shield-check-line text-success"></i> AI hanya memberi saran dan draf. Tidak ada yang terkirim atau berubah di bizbos tanpa Anda.</div>
                    </div>
                </div>

                <div class="card-footer bg-white border-top p-2">
                    <form id="ai-form" class="d-flex flex-column gap-2" autocomplete="off">
                        <textarea id="ai-input" rows="2" maxlength="8000" class="form-control" placeholder="Contoh: Buatkan caption promo bizbos untuk pemilik les musik, gaya santai, 3 versi…" @disabled(! $provider)></textarea>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <label for="ai-model" class="small text-muted mb-0" title="Model = 'otak' AI yang menjawab. Model besar (Opus, GPT-6) lebih pintar tapi lebih lama dan lebih mahal. Model kecil (Haiku, Flash) cepat dan hemat.">
                                <i class="ri-brain-line"></i> Otak AI <i class="ri-question-line"></i>
                            </label>
                            <select id="ai-model" class="form-select form-select-sm w-auto" style="max-width: 260px" @disabled(! $provider)>
                                @foreach ($models as $m)
                                    <option value="{{ $m['id'] }}" @selected($m['id'] === $model)>{{ $m['name'] }}{{ $m['name'] !== $m['id'] ? ' ('.$m['id'].')' : '' }}</option>
                                @endforeach
                            </select>
                            <span class="small text-muted flex-grow-1" id="ai-status">
                                @if ($provider)
                                    Sisa hari ini: {{ max(0, \App\Http\Controllers\Superadmin\Ai\AssistantController::DAILY_LIMIT - $usedToday) }} dari {{ \App\Http\Controllers\Superadmin\Ai\AssistantController::DAILY_LIMIT }} pesan
                                @endif
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="ai-stop" hidden><i class="ri-stop-circle-line"></i> Stop</button>
                            <button type="submit" class="btn btn-sm btn-primary px-3" id="ai-send" @disabled(! $provider)><i class="ri-send-plane-2-line"></i> Kirim</button>
                        </div>
                        <div class="small text-muted"><kbd>Enter</kbd> kirim · <kbd>Shift</kbd>+<kbd>Enter</kbd> baris baru · Tips: sebutkan untuk siapa, tujuannya apa, dan panjangnya berapa, supaya hasilnya pas.</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var thread = document.getElementById('ai-thread');
        var form = document.getElementById('ai-form');
        var input = document.getElementById('ai-input');
        var modelSelect = document.getElementById('ai-model');
        var sendButton = document.getElementById('ai-send');
        var stopButton = document.getElementById('ai-stop');
        var status = document.getElementById('ai-status');
        var welcome = document.getElementById('ai-welcome');
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var sendUrl = {{ \Illuminate\Support\Js::from(route('superadmin.ai-assistant.send')) }};
        var conversationId = {{ \Illuminate\Support\Js::from($current?->id) }};
        var initial = {{ \Illuminate\Support\Js::from($messages->map(fn ($m) => [
            'role' => $m->role, 'content' => $m->content, 'model' => $m->model, 'input_tokens' => $m->input_tokens,
            'output_tokens' => $m->output_tokens, 'duration_ms' => $m->duration_ms, 'status' => $m->status,
        ])->values()) }};
        var controller = null;

        // Teks dari AI selalu di-escape dulu; baru setelah itu diberi format sederhana. Tidak ada HTML dari AI yang dijalankan.
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
                if (i % 2 === 1) {
                    return '<pre><code>' + escapeHtml(part.replace(/^[^\n]*\n/, '')) + '</code></pre>';
                }
                return escapeHtml(part).split('\n').map(function (line) {
                    var heading = line.match(/^#{1,4}\s+(.*)$/);
                    return heading ? '<strong>' + inline(heading[1]) + '</strong>' : inline(line);
                }).join('<br>');
            }).join('');
        };
        var meta = function (m) {
            var parts = [];
            if (m.model) parts.push(m.model);
            if (m.duration_ms) parts.push('dijawab dalam ' + (m.duration_ms / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' detik');
            if (m.input_tokens || m.output_tokens) parts.push(((m.input_tokens || 0) + (m.output_tokens || 0)).toLocaleString('id-ID') + ' token');
            if (m.status === 'stopped') parts.push('dihentikan');
            return parts.join(' · ');
        };

        var bubble = function (role, content, info) {
            welcome.hidden = true;
            var row = document.createElement('div');
            row.className = 'd-flex mb-3 ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');
            var box = document.createElement('div');
            box.className = 'ai-bubble rounded-3 px-3 py-2 ' + (role === 'user' ? 'ai-user' : 'bg-light');
            var body = document.createElement('div');
            body.innerHTML = render(content);
            box.appendChild(body);
            var foot = document.createElement('div');
            foot.className = 'd-flex flex-wrap align-items-center gap-2 mt-1 small ' + (role === 'user' ? 'text-white-50' : 'text-muted');
            var metaText = document.createElement('span');
            metaText.textContent = info || '';
            foot.appendChild(metaText);
            if (role === 'assistant') {
                var copy = document.createElement('button');
                copy.type = 'button';
                copy.className = 'btn btn-link btn-sm p-0 text-muted';
                copy.innerHTML = '<i class="ri-file-copy-line"></i> Salin jawaban';
                copy.addEventListener('click', function () {
                    var done = function () { copy.innerHTML = '<i class="ri-check-line"></i> Tersalin'; setTimeout(function () { copy.innerHTML = '<i class="ri-file-copy-line"></i> Salin jawaban'; }, 1500); };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(box.dataset.raw || '').then(done);
                    } else {
                        var temp = document.createElement('textarea');
                        temp.value = box.dataset.raw || '';
                        document.body.appendChild(temp); temp.select(); document.execCommand('copy'); temp.remove(); done();
                    }
                });
                foot.appendChild(copy);
            }
            box.appendChild(foot);
            box.dataset.raw = content;
            row.appendChild(box);
            thread.appendChild(row);
            thread.scrollTop = thread.scrollHeight;
            return { box: box, body: body, meta: metaText };
        };

        initial.forEach(function (m) { bubble(m.role, m.content, m.role === 'assistant' ? meta(m) : ''); });

        var busy = function (on) {
            sendButton.disabled = on;
            input.disabled = on;
            modelSelect.disabled = on;
            stopButton.hidden = !on;
        };

        var addToList = function (event) {
            var list = document.getElementById('ai-list');
            var empty = list.querySelector('[data-empty]');
            if (empty) empty.remove();
            list.querySelectorAll('a.active').forEach(function (a) { a.classList.remove('active', 'fw-semibold'); });
            var link = document.createElement('a');
            link.href = event.url;
            link.className = 'list-group-item list-group-item-action border-0 rounded px-2 py-2 active fw-semibold';
            var title = document.createElement('div');
            title.className = 'text-truncate';
            title.textContent = event.title;
            link.appendChild(title);
            list.prepend(link);
        };

        var send = function () {
            var text = input.value.trim();
            if (!text || sendButton.disabled) return;

            bubble('user', text, '');
            input.value = '';
            var answer = bubble('assistant', '', '');
            answer.body.classList.add('ai-typing');
            var full = '';
            var failed = null;
            busy(true);
            status.textContent = '🤔 AI sedang berpikir…';
            controller = new AbortController();

            fetch(sendUrl, {
                method: 'POST',
                signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream, application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message: text, conversation_id: conversationId, model: modelSelect.value })
            }).then(function (response) {
                var type = response.headers.get('Content-Type') || '';
                if (!response.ok || type.indexOf('text/event-stream') === -1) {
                    return response.json().catch(function () { return {}; }).then(function (data) {
                        var message = data.message;
                        if (!message && data.errors) message = Object.values(data.errors)[0][0];
                        if (response.status === 419) message = 'Sesi Anda sudah habis. Muat ulang halaman ini, lalu kirim lagi.';
                        throw new Error(message || 'Pesan gagal terkirim (kode ' + response.status + '). Coba lagi ya.');
                    });
                }

                var reader = response.body.getReader();
                var decoder = new TextDecoder();
                var buffer = '';
                var handle = function (event) {
                    if (event.t === 'start') {
                        if (!conversationId) {
                            conversationId = event.conversation_id;
                            history.replaceState(null, '', event.url);
                            addToList(event);
                        }
                        status.textContent = '✍️ AI sedang menulis jawaban…';
                    } else if (event.t === 'delta') {
                        full += event.text;
                        answer.body.innerHTML = render(full);
                        answer.box.dataset.raw = full;
                        thread.scrollTop = thread.scrollHeight;
                    } else if (event.t === 'done') {
                        answer.meta.textContent = meta(event);
                    } else if (event.t === 'error') {
                        failed = event.message;
                    }
                };
                var pump = function () {
                    return reader.read().then(function (chunk) {
                        if (chunk.done) return;
                        buffer += decoder.decode(chunk.value, { stream: true });
                        var parts = buffer.split('\n\n');
                        buffer = parts.pop();
                        parts.forEach(function (part) {
                            part.split('\n').forEach(function (line) {
                                if (line.indexOf('data: ') === 0) {
                                    try { handle(JSON.parse(line.slice(6))); } catch (e) {}
                                }
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
                    answer.box.classList.remove('bg-light');
                    answer.box.classList.add('bg-danger-subtle');
                    answer.body.textContent = '⚠️ ' + failed;
                } else if (!full) {
                    answer.body.textContent = '(Jawaban dihentikan)';
                }
                busy(false);
                status.textContent = failed ? 'Ada kendala. Coba kirim lagi, atau pilih otak AI lain.' : '✅ Selesai. Silakan lanjut bertanya atau salin jawabannya.';
                controller = null;
                input.focus();
            });
        };

        form.addEventListener('submit', function (event) { event.preventDefault(); send(); });
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); send(); }
        });
        stopButton.addEventListener('click', function () { if (controller) controller.abort(); });
        // Contoh pertanyaan: hanya mengisi kotak pesan, supaya bisa disesuaikan dulu sebelum dikirim.
        document.querySelectorAll('[data-example]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (input.disabled) return;
                input.value = button.dataset.example;
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            });
        });
        thread.scrollTop = thread.scrollHeight;
        if (!input.disabled) input.focus();
    })();
</script>
@endpush
