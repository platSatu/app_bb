@php($timeLimit = $page->setting('quiz.time_limit'))
<div class="card shadow-sm">
    <div class="card-body p-4 p-md-5">
        @if ($result = session('quiz_result'))
            <div class="text-center">
                <i class="ri-trophy-line fs-1 text-warning"></i>
                <h1 class="h4 mt-2">Terima kasih sudah mengerjakan</h1>
                <p class="text-muted mb-1">Skor Anda</p>
                <p class="display-6 fw-semibold mb-1">{{ $result['score'] }} <span class="fs-5 text-muted">/ {{ $result['max'] }}</span></p>
                @if ($result['late'])
                    <p class="text-danger small mb-1">Jawaban dikirim setelah waktu habis, jadi tidak dihitung.</p>
                @endif
                <p class="text-muted small mb-4">Nomor: {{ $result['reference'] }}</p>
                @if ($page->setting('quiz.allow_retry', false))
                    <a href="{{ $page->url() }}" class="btn btn-outline-secondary">Kerjakan lagi</a>
                @endif
            </div>
        @else
            <h1 class="h3 mb-2 text-center">{{ $page->name }}</h1>
            @if ($page->description)
                <p class="text-muted text-center" style="white-space: pre-line;">{{ $page->description }}</p>
            @endif

            <div class="d-flex justify-content-center flex-wrap gap-3 small text-muted mb-4">
                <span><i class="ri-question-line"></i> {{ $questionCount }} soal</span>
                <span><i class="ri-time-line"></i> {{ $timeLimit ? $timeLimit.' menit' : 'Tanpa batas waktu' }}</span>
                <span><i class="ri-repeat-line"></i> {{ $page->setting('quiz.allow_retry', false) ? 'Boleh diulang' : '1 kali per nomor' }}</span>
            </div>

            @if ($questionCount === 0)
                <p class="text-center text-muted mb-0">Soal quiz sedang disiapkan.</p>
            @else
                <form method="POST" action="{{ route('page.quiz.start', $page->slug) }}" onsubmit="this.querySelector('[type=submit]').disabled = true">
                    @csrf
                    @include('page.public._honeypot')
                    @foreach (['name' => ['Nama', 'text', true], 'phone' => ['No. WhatsApp', 'tel', true], 'email' => ['Email (opsional)', 'email', false]] as $field => [$label, $type, $required])
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                            <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" class="form-control @error($field) is-invalid @enderror" @required($required)>
                            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                    @include('auth.partials.turnstile')
                    <button type="submit" class="btn btn-primary w-100">Mulai Quiz</button>
                    @if ($timeLimit)
                        <div class="form-text text-center">Waktu mulai berjalan setelah tombol ini diklik.</div>
                    @endif
                </form>
            @endif
        @endif
    </div>
</div>
