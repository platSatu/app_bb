@extends('layouts.dashboard')

@section('title', 'Hasil Quiz')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'quiz-attempts'])

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nomor, nama, atau WhatsApp" value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-3">
                    <select name="state" class="form-select">
                        <option value="">Semua</option>
                        <option value="finished" @selected(request('state') === 'finished')>Selesai</option>
                        <option value="unfinished" @selected(request('state') === 'unfinished')>Belum selesai</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'state']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nomor</th>
                            <th>Peserta</th>
                            <th class="text-center">Skor</th>
                            <th class="text-center">Benar</th>
                            <th class="text-center">Durasi</th>
                            <th>Mulai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attempts as $attempt)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $attempt->reference }}</td>
                                <td>{{ $attempt->name }}<div class="text-muted small">+{{ $attempt->phone }}</div></td>
                                <td class="text-center text-nowrap">
                                    @if ($attempt->isFinished())
                                        <span class="fw-semibold">{{ $attempt->score }}</span> / {{ $attempt->max_score }}
                                        @if ($attempt->is_late) <div class="badge bg-danger-subtle text-danger">Lewat waktu</div> @endif
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Belum selesai</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $attempt->isFinished() ? $attempt->correct_count : '-' }}</td>
                                <td class="text-center">{{ $attempt->durationLabel() }}</td>
                                <td class="text-nowrap small">{{ $attempt->started_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route($routes.'.quiz.attempts.show', [$page, $attempt]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'state']) ? 'Tidak ada hasil yang cocok.' : 'Belum ada peserta. Bagikan link atau QR code quiz ini.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $attempts->links() }}</div>
        </div>
    </div>
@endsection
