@extends('page.public.layout')

@section('title', $page->name)

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

        @foreach ($questions as $question)
            @php($name = 'answers['.$question->id.']')
            <div class="card quiz-card shadow-sm mb-3" data-question>
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
                </div>
            </div>
        @endforeach

        <div class="card quiz-card shadow-sm mb-5">
            <div class="card-body p-4 text-center">
                <p class="text-muted small mb-3" id="quiz-remaining"></p>
                <button type="submit" class="btn btn-brand px-5 py-2" onclick="return confirm('Kirim jawaban sekarang? Jawaban tidak bisa diubah lagi.')"><i class="ri-send-plane-line me-1"></i>Kirim Jawaban</button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.getElementById('quiz-play');
                var cards = form.querySelectorAll('[data-question]');

                function progress() {
                    var answered = 0;
                    cards.forEach(function (card) {
                        var done = Array.prototype.some.call(card.querySelectorAll('input, textarea'), function (el) {
                            return el.type === 'radio' ? el.checked : el.value.trim() !== '';
                        });
                        if (done) answered++;
                        card.querySelectorAll('.q-option').forEach(function (option) {
                            option.classList.toggle('is-checked', option.querySelector('input').checked);
                        });
                    });
                    var left = cards.length - answered;
                    document.getElementById('quiz-answered').textContent = answered;
                    document.getElementById('quiz-progress').style.width = (cards.length ? answered / cards.length * 100 : 0) + '%';
                    document.getElementById('quiz-remaining').textContent = left ? 'Masih ada ' + left + ' soal yang belum dijawab.' : 'Semua soal sudah dijawab. Siap dikirim!';
                }
                form.addEventListener('change', progress);
                form.addEventListener('input', progress);
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
