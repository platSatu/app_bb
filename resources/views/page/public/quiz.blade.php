@php($timeLimit = $page->setting('quiz.time_limit'))
@push('head')
    <style>
        .quiz-card { border: 0; border-radius: 1rem; }
        .quiz-stat { background: color-mix(in srgb, var(--brand) 6%, #fff); border-radius: .875rem; padding: .875rem; text-align: center; height: 100%; }
        .quiz-stat i { font-size: 1.4rem; color: var(--brand); }
        .quiz-badge { width: 84px; height: 84px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; background: color-mix(in srgb, var(--brand) 12%, #fff); color: var(--brand); }
        .chip { background: #f3f4f6; border-radius: 999px; padding: .25rem .75rem; font-size: .8125rem; }
    </style>
@endpush

@if ($result = session('quiz_result'))
    @php($percent = $result['max'] > 0 ? (int) round($result['score'] / $result['max'] * 100) : 0)
    <div class="card quiz-card shadow-sm">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="quiz-badge mb-3"><i class="ri-trophy-line"></i></span>
            <h1 class="h4 fw-bold mb-1">Terima kasih sudah mengerjakan!</h1>
            <p class="text-muted mb-4">{{ $page->name }}</p>

            <div class="mx-auto" style="max-width: 360px;">
                <div class="text-muted small mb-1">Skor Anda</div>
                <div class="display-5 fw-bold mb-2">{{ $result['score'] }} <span class="fs-4 text-muted fw-normal">/ {{ $result['max'] }}</span></div>
                @if ($result['max'] > 0)
                    <div class="progress mb-1" style="height: 10px;" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $percent }}%; background: var(--brand);"></div>
                    </div>
                    <div class="small text-muted mb-3">{{ $percent }}% benar</div>
                @endif
            </div>

            @if ($result['late'])
                <div class="alert alert-danger small py-2 mx-auto" style="max-width: 420px;">Jawaban dikirim setelah waktu habis, jadi tidak dihitung.</div>
            @endif

            <span class="chip"><i class="ri-hashtag me-1"></i>{{ $result['reference'] }}</span>

            @if ($page->setting('quiz.allow_retry', false))
                <div class="mt-4"><a href="{{ $page->url() }}" class="btn btn-brand px-4"><i class="ri-restart-line me-1"></i>Kerjakan lagi</a></div>
            @endif
        </div>
    </div>
@else
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card quiz-card shadow-sm h-100">
                <div class="card-body p-4 p-md-5">
                    <span class="quiz-badge mb-3" style="width: 64px; height: 64px; font-size: 1.9rem;"><i class="ri-questionnaire-line"></i></span>
                    <h1 class="h3 fw-bold mb-2">{{ $page->name }}</h1>
                    @if ($page->description)
                        <p class="text-muted" style="white-space: pre-line;">{{ $page->description }}</p>
                    @endif

                    <div class="row g-2 mt-3">
                        @foreach ([
                            ['ri-question-line', $questionCount.' soal', 'Jumlah soal'],
                            ['ri-time-line', $timeLimit ? $timeLimit.' menit' : 'Bebas', 'Waktu'],
                            ['ri-repeat-line', $page->setting('quiz.allow_retry', false) ? 'Boleh' : '1 kali', 'Ulangi'],
                        ] as [$icon, $value, $label])
                            <div class="col-4">
                                <div class="quiz-stat">
                                    <i class="{{ $icon }}"></i>
                                    <div class="fw-semibold mt-1">{{ $value }}</div>
                                    <div class="small text-muted">{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card quiz-card shadow-sm h-100">
                <div class="card-body p-4 p-md-5">
                    @if ($questionCount === 0)
                        <div class="text-center text-muted py-5"><i class="ri-time-line fs-1 d-block mb-2"></i>Soal quiz sedang disiapkan. Coba lagi nanti ya.</div>
                    @else
                        <h2 class="h5 fw-semibold mb-1">Data Peserta</h2>
                        <p class="text-muted small mb-4">Isi data di bawah, lalu klik Mulai Quiz.</p>
                        <form method="POST" action="{{ route('page.quiz.start', $page->slug) }}" onsubmit="this.querySelector('[type=submit]').disabled = true">
                            @csrf
                            @include('page.public._honeypot')
                            @foreach (['name' => ['Nama lengkap', 'text', true], 'phone' => ['No. WhatsApp', 'tel', true], 'email' => ['Email (opsional)', 'email', false]] as $field => [$label, $type, $required])
                                <div class="mb-3">
                                    <label for="{{ $field }}" class="form-label mb-1">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                    <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" @required($required)>
                                    @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endforeach
                            <div class="mb-3">@include('auth.partials.turnstile')</div>
                            <button type="submit" class="btn btn-brand w-100 py-2"><i class="ri-play-circle-line me-1"></i>Mulai Quiz</button>
                            @if ($timeLimit)
                                <div class="form-text text-center mt-2">Waktu {{ $timeLimit }} menit mulai berjalan setelah tombol ini diklik.</div>
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
