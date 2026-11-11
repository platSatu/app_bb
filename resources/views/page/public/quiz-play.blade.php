@extends('page.public.layout')

@section('title', $page->name)
@section('width', '1040px')

@push('head')
    <style>
        .quiz-card { border: 0; border-radius: 1rem; }
        .quiz-head { position: sticky; top: .75rem; z-index: 10; }
        .q-number { width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: #fff; font-weight: 600; font-size: .875rem; display: inline-flex; align-items: center; justify-content: center; }
        .q-option { display: flex; align-items: center; gap: .75rem; border: 1px solid #e9ecef; border-radius: .75rem; padding: .75rem 1rem; cursor: pointer; transition: border-color .15s, background .15s; }
        .q-option:hover { border-color: color-mix(in srgb, var(--brand) 50%, #fff); }
        .q-option.is-checked { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 6%, #fff); }
        .q-option .form-check-input { margin: 0; flex-shrink: 0; }
        .timer-pill { border-radius: 999px; padding: .35rem .85rem; font-weight: 600; background: #212529; color: #fff; }
        .timer-pill.is-late { background: #dc3545; }
        /* Navigasi: satu soal tampil, nomor soal di samping (di HP: di atas soal). */
        .q-nav-card { position: sticky; top: 7.5rem; }
        .q-nav { display: grid; grid-template-columns: repeat(auto-fill, minmax(42px, 1fr)); gap: .5rem; }
        .q-nav button { height: 42px; border-radius: .6rem; border: 1px solid #dee2e6; background: #fff; font-weight: 600; color: #495057; }
        .q-nav button.is-answered { background: var(--brand); border-color: var(--brand); color: #fff; }
        .q-nav button.is-current { outline: 3px solid color-mix(in srgb, var(--brand) 35%, #fff); outline-offset: 1px; }
        .q-nav button.is-missing { border-color: #dc3545; color: #dc3545; background: #fff5f5; }
        .q-legend span { display: inline-block; width: 12px; height: 12px; border-radius: 3px; vertical-align: -1px; margin-right: .25rem; }
        @media (max-width: 991.98px) { .q-nav-card { position: static; } }
    </style>
@endpush

@section('content')
    <form method="POST" action="{{ route('page.quiz.submit', $page->slug) }}" id="quiz-play">
        @csrf
        <div class="card quiz-card shadow-sm mb-3 quiz-head">
            <div class="card-body py-3 px-4">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                    <div class="fw-semibold text-truncate">{{ $page->name }}</div>
                    @if ($deadline)
                        <span class="timer-pill text-nowrap" id="timer" data-deadline="{{ $deadline->getTimestamp() }}" data-now="{{ now()->getTimestamp() }}"><i class="ri-time-line me-1"></i><span>--:--</span></span>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2 small text-muted">
                    <div class="progress flex-grow-1" style="height: 6px;"><div class="progress-bar" id="quiz-progress" style="width: 0%; background: var(--brand);"></div></div>
                    <span class="text-nowrap"><span id="quiz-answered">0</span>/{{ $questions->count() }} terjawab</span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8 order-2 order-lg-1">
        @foreach ($questions as $question)
            @php($name = 'answers['.$question->id.']')
            <div class="card quiz-card shadow-sm mb-3 {{ $loop->first ? '' : 'd-none' }}" data-question data-index="{{ $loop->index }}">
                <div class="card-body p-4">
                    <div class="d-flex gap-3 mb-3">
                        <span class="q-number flex-shrink-0">{{ $loop->iteration }}</span>
                        <div class="flex-grow-1 fw-semibold pt-1" style="white-space: pre-line;">{{ $question->question }}</div>
                        @if ($question->isScored())
                            <span class="badge bg-light text-dark text-nowrap align-self-start">{{ $question->points }} poin</span>
                        @endif
                    </div>
                    @switch($question->type)
                        @case(\App\Models\QuizQuestion::TYPE_SINGLE)
                            <div class="d-grid gap-2">
                                @foreach ($question->options as $option)
                                    <label class="q-option" for="o-{{ $option->id }}">
                                        <input class="form-check-input" type="radio" name="{{ $name }}" id="o-{{ $option->id }}" value="{{ $option->id }}">
                                        <span>{{ $option->label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break
                        @case(\App\Models\QuizQuestion::TYPE_TEXTAREA)
                            <textarea name="{{ $name }}" rows="4" maxlength="5000" class="form-control" placeholder="Tulis jawaban Anda"></textarea>
                            @break
                        @default
                            <input type="text" name="{{ $name }}" maxlength="255" class="form-control" placeholder="Jawaban Anda" autocomplete="off">
                    @endswitch
                    <div class="d-flex justify-content-between align-items-center gap-2 mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-light" data-go="{{ $loop->index - 1 }}" @disabled($loop->first)><i class="ri-arrow-left-s-line"></i> Sebelumnya</button>
                        <span class="small text-muted text-nowrap">{{ $loop->iteration }} / {{ $loop->count }}</span>
                        @if ($loop->last)
                            <button type="submit" class="btn btn-brand"><i class="ri-send-plane-line me-1"></i>Kirim</button>
                        @else
                            <button type="button" class="btn btn-brand" data-go="{{ $loop->index + 1 }}">Berikutnya <i class="ri-arrow-right-s-line"></i></button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

                {{-- Pemeriksaan sebelum kirim: soal yang belum dijawab (bisa langsung dibuka, atau tetap dikirim). --}}
                <div class="alert alert-warning d-none" id="quiz-check" role="alert">
                    <div class="fw-semibold mb-1"><i class="ri-error-warning-line me-1"></i><span id="quiz-check-text"></span></div>
                    <div class="small mb-2">Klik nomornya untuk menjawab, atau kirim saja kalau memang sengaja dikosongkan.</div>
                    <div class="d-flex flex-wrap gap-2" id="quiz-check-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-dark mt-3" id="quiz-force">Tetap kirim jawaban</button>
                </div>
            </div>

            <div class="col-lg-4 order-1 order-lg-2">
                <div class="card quiz-card shadow-sm q-nav-card">
                    <div class="card-body p-3 p-lg-4">
                        <div class="fw-semibold mb-3">Nomor soal</div>
                        <div class="q-nav mb-3">
                            @foreach ($questions as $question)
                                <button type="button" data-go="{{ $loop->index }}" aria-label="Soal {{ $loop->iteration }}">{{ $loop->iteration }}</button>
                            @endforeach
                        </div>
                        <div class="q-legend small text-muted d-flex flex-wrap gap-3 mb-3">
                            <div><span style="background: var(--brand)"></span>Sudah dijawab</div>
                            <div><span style="border: 1px solid #dee2e6"></span>Belum</div>
                        </div>
                        <p class="text-muted small mb-2" id="quiz-remaining"></p>
                        <button type="submit" class="btn btn-brand w-100"><i class="ri-send-plane-line me-1"></i>Kirim Jawaban</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.getElementById('quiz-play');
                var cards = form.querySelectorAll('[data-question]');
                var navs = form.querySelectorAll('.q-nav button');
                var check = document.getElementById('quiz-check');
                var current = 0, force = false;

                function isAnswered(card) {
                    return Array.prototype.some.call(card.querySelectorAll('input, textarea'), function (el) {
                        return el.type === 'radio' ? el.checked : el.value.trim() !== '';
                    });
                }

                function missing() {
                    var list = [];
                    cards.forEach(function (card, i) { if (!isAnswered(card)) list.push(i); });
                    return list;
                }

                // Tampilkan satu soal; nomor di samping ikut menandai soal yang sedang dibuka.
                function show(index, scroll) {
                    if (index < 0 || index >= cards.length) return;
                    current = index;
                    cards.forEach(function (card, i) { card.classList.toggle('d-none', i !== index); });
                    navs.forEach(function (nav, i) { nav.classList.toggle('is-current', i === index); });
                    if (scroll !== false && window.innerWidth < 992) cards[index].scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                function progress() {
                    var answered = 0;
                    cards.forEach(function (card, i) {
                        var done = isAnswered(card);
                        if (done) answered++;
                        navs[i].classList.toggle('is-answered', done);
                        if (done) navs[i].classList.remove('is-missing');
                        card.querySelectorAll('.q-option').forEach(function (option) {
                            option.classList.toggle('is-checked', option.querySelector('input').checked);
                        });
                    });
                    var left = cards.length - answered;
                    document.getElementById('quiz-answered').textContent = answered;
                    document.getElementById('quiz-progress').style.width = (cards.length ? answered / cards.length * 100 : 0) + '%';
                    document.getElementById('quiz-remaining').textContent = left ? 'Masih ada ' + left + ' soal yang belum dijawab.' : 'Semua soal sudah dijawab. Siap dikirim!';
                    if (!left) check.classList.add('d-none');
                }

                form.addEventListener('click', function (e) {
                    var go = e.target.closest('[data-go]');
                    if (go) show(Number(go.dataset.go));
                });
                form.addEventListener('change', progress);
                form.addEventListener('input', progress);

                // Sebelum kirim: kalau masih ada yang kosong, beri tahu nomornya dulu (waktu habis tetap terkirim otomatis).
                form.addEventListener('submit', function (e) {
                    var empty = missing();
                    if (empty.length && !force) {
                        e.preventDefault();
                        document.getElementById('quiz-check-text').textContent = empty.length + ' soal belum dijawab: nomor ' + empty.map(function (i) { return i + 1; }).join(', ') + '.';
                        var list = document.getElementById('quiz-check-list');
                        list.innerHTML = '';
                        empty.forEach(function (i) {
                            navs[i].classList.add('is-missing');
                            var b = document.createElement('button');
                            b.type = 'button'; b.className = 'btn btn-sm btn-outline-danger'; b.dataset.go = i; b.textContent = 'Soal ' + (i + 1);
                            list.appendChild(b);
                        });
                        check.classList.remove('d-none');
                        check.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    if (!confirm('Kirim jawaban sekarang? Jawaban tidak bisa diubah lagi.')) { e.preventDefault(); force = false; }
                });
                document.getElementById('quiz-force').addEventListener('click', function () { force = true; form.requestSubmit(); });

                show(0, false);
                progress();

                var timer = document.getElementById('timer');
                if (!timer) return;
                // Selisih jam browser vs server dikoreksi; batas resmi tetap dicek di server.
                var offset = Number(timer.dataset.now) * 1000 - Date.now();
                var deadline = Number(timer.dataset.deadline) * 1000;
                var label = timer.querySelector('span');

                function tick() {
                    var left = Math.max(0, Math.round((deadline - (Date.now() + offset)) / 1000));
                    label.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
                    timer.classList.toggle('is-late', left <= 60);
                    if (left === 0) { clearInterval(interval); form.submit(); }
                }
                var interval = setInterval(tick, 1000);
                tick();
            })();
        </script>
    @endpush
@endsection
