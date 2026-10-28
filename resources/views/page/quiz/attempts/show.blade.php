@extends('layouts.dashboard')

@section('title', 'Detail Hasil Quiz')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'quiz-attempts'])

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="mb-1 fw-semibold">{{ $attempt->name }} · {{ $attempt->reference }}</h6>
                <div class="text-muted small lh-sm">+{{ $attempt->phone }}{{ $attempt->email ? ' · '.$attempt->email : '' }} · mulai {{ $attempt->started_at->translatedFormat('d M Y H:i') }} · durasi {{ $attempt->durationLabel() }}</div>
            </div>
            <div class="text-end">
                <div class="fs-4 fw-semibold">{{ $attempt->score }} <span class="fs-6 text-muted">/ {{ $attempt->max_score }}</span></div>
                @if ($attempt->is_late) <span class="badge bg-danger-subtle text-danger">Lewat batas waktu, tidak dihitung</span> @endif
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            @forelse ($attempt->answers as $answer)
                <div class="border rounded-3 p-3 mb-2">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="fw-semibold">{{ $loop->iteration }}. {{ $answer->question }}</div>
                        @if ($answer->is_correct === true)
                            <span class="badge bg-success-subtle text-success text-nowrap">Benar +{{ $answer->points }}</span>
                        @elseif ($answer->is_correct === false)
                            <span class="badge bg-danger-subtle text-danger text-nowrap">Salah</span>
                        @else
                            <span class="badge bg-light text-dark text-nowrap">Tidak dinilai</span>
                        @endif
                    </div>
                    <div class="mt-1" style="white-space: pre-line;">{{ filled($answer->answer) ? $answer->answer : '— tidak dijawab —' }}</div>
                </div>
            @empty
                <div class="text-center text-muted py-4">Peserta belum menyelesaikan quiz.</div>
            @endforelse

            <a href="{{ route($routes.'.quiz.attempts.index', $page) }}" class="btn btn-light mt-2"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
    </div>
@endsection
