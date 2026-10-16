@extends('page.public.layout')

@section('title', $page->name)

@section('content')
    <form method="POST" action="{{ route('page.quiz.submit', $page->slug) }}" id="quiz-play">
        @csrf
        <div class="card shadow-sm mb-3 position-sticky" style="top: 0; z-index: 10;">
            <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
                <div class="fw-semibold small">{{ $page->name }}</div>
                @if ($deadline)
                    <div class="badge bg-dark fs-6" id="timer" data-deadline="{{ $deadline->getTimestamp() }}" data-now="{{ now()->getTimestamp() }}">--:--</div>
                @endif
            </div>
        </div>

        @foreach ($questions as $question)
            @php($name = 'answers['.$question->id.']')
            <div class="card shadow-sm mb-3">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <div class="fw-semibold" style="white-space: pre-line;">{{ $loop->iteration }}. {{ $question->question }}</div>
                        @if ($question->isScored())
                            <span class="badge bg-light text-dark text-nowrap align-self-start">{{ $question->points }} poin</span>
                        @endif
                    </div>
                    @switch($question->type)
                        @case(\App\Models\QuizQuestion::TYPE_SINGLE)
                            @foreach ($question->options as $option)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="{{ $name }}" id="o-{{ $option->id }}" value="{{ $option->id }}">
                                    <label class="form-check-label" for="o-{{ $option->id }}">{{ $option->label }}</label>
                                </div>
                            @endforeach
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

        <button type="submit" class="btn btn-primary w-100 mb-5" onclick="return confirm('Kirim jawaban sekarang? Jawaban tidak bisa diubah lagi.')">Kirim Jawaban</button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var timer = document.getElementById('timer');
                if (!timer) return;
                // Selisih jam browser vs server dikoreksi; batas resmi tetap dicek di server.
                var offset = Number(timer.dataset.now) * 1000 - Date.now();
                var deadline = Number(timer.dataset.deadline) * 1000;
                var form = document.getElementById('quiz-play');

                function tick() {
                    var left = Math.max(0, Math.round((deadline - (Date.now() + offset)) / 1000));
                    timer.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
                    timer.classList.toggle('bg-danger', left <= 60);
                    if (left === 0) { clearInterval(interval); form.submit(); }
                }
                var interval = setInterval(tick, 1000);
                tick();
            })();
        </script>
    @endpush
@endsection
