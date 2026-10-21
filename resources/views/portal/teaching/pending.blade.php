@extends('layouts.dashboard')

@section('page-section', 'Pengajar')

@section('title', 'Absen Murid')

@section('content')
    <div class="pb-4">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('portal.absen') }}" class="btn btn-sm rounded-pill {{ $done ? 'btn-light' : 'btn-primary' }}">
            Perlu diisi @if ($toFill) <span class="badge bg-danger rounded-pill ms-1">{{ $toFill }}</span> @endif
        </a>
        <a href="{{ route('portal.absen', ['lihat' => 'selesai']) }}" class="btn btn-sm rounded-pill {{ $done ? 'btn-primary' : 'btn-light' }}">Sudah diisi (30 hari)</a>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($sessions->isEmpty())
                @include('portal._empty', $done
                    ? ['icon' => 'ri-file-list-3-line', 'title' => 'Belum ada absen yang diisi 30 hari terakhir']
                    : ['icon' => 'ri-emotion-happy-line', 'title' => 'Semua absen sudah diisi', 'text' => 'Sesi yang sudah dimulai akan muncul di sini supaya tidak terlewat.'])
            @else
                @unless ($done)
                    <p class="small text-muted mb-3">Isi absen setelah sesi dimulai. Kalau ada yang salah setelah disimpan, minta admin untuk mengoreksinya.</p>
                @endunless
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hari & Tanggal</th>
                                <th>Jam</th>
                                <th>Pelajaran</th>
                                <th>Murid</th>
                                @if ($showBranch) <th>Tempat</th> @endif
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $session->start_at->translatedFormat('l') }}</div>
                                        <div class="small text-muted">{{ $session->start_at->translatedFormat('d F Y') }}</div>
                                    </td>
                                    <td class="text-nowrap">{{ $session->timeRange() }}</td>
                                    <td>{{ $session->grade?->label() }}</td>
                                    <td>{{ $session->attendances->pluck('student.name')->filter()->join(', ') ?: '-' }}</td>
                                    @if ($showBranch) <td>@include('portal._branch', ['branch' => $session->branch])</td> @endif
                                    <td class="text-end">@include('portal.teaching._action')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $sessions->links() }}</div>
            @endif
        </div>
    </div>
    </div>
@endsection
