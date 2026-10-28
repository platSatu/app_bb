@extends('layouts.dashboard')

@section('title', 'Pengaturan Quiz')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'quiz-settings'])

    <div class="card" style="max-width: 640px;">
        <div class="card-header bg-white border-bottom px-4 py-3">
            <h6 class="mb-1 fw-semibold">Pengaturan Quiz</h6>
            <div class="text-muted small lh-sm">Peserta hanya melihat skor setelah selesai.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="{{ route($routes.'.quiz.settings.update', $page) }}">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $canManage)>
                    <div class="mb-3">
                        <label for="time_limit" class="form-label">Batas Waktu</label>
                        <div class="input-group" style="max-width: 240px;">
                            <input type="number" id="time_limit" name="time_limit" min="1" max="600" class="form-control @error('time_limit') is-invalid @enderror"
                                value="{{ old('time_limit', $page->setting('quiz.time_limit')) }}" placeholder="Tanpa batas">
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Kosongkan kalau tanpa batas waktu. Waktu dihitung di server sejak peserta mulai.</div>
                        @error('time_limit') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="allow_retry" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="allow_retry" name="allow_retry" value="1" @checked(old('allow_retry', $page->setting('quiz.allow_retry', false)))>
                        <label class="form-check-label" for="allow_retry">Boleh mengerjakan berkali-kali</label>
                        <div class="form-text">Kalau dimatikan, satu nomor WhatsApp hanya bisa mengerjakan 1 kali.</div>
                    </div>
                    @if ($canManage)
                        <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Simpan</button></div>
                    @endif
                </fieldset>
            </form>
        </div>
    </div>
@endsection
