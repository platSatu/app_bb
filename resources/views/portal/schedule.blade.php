@extends('layouts.dashboard')

@section('page-section', 'Student')

@section('title', 'Jadwal Les')

@php($showBranch = count($colors) > 1)

@section('content')
    <div class="pb-4">
    @include('portal._tabs', ['param' => 'anak'])

    <div class="card">
        <div class="card-body">
            @if (! $sessions || $sessions->isEmpty())
                @include('portal._empty', ['title' => 'Belum ada jadwal les yang akan datang', 'text' => 'Kalau seharusnya sudah ada, coba tanyakan ke admin tempat les.'])
            @else
                <p class="small text-muted mb-3">Jadwal mulai hari ini. Kalau ada perubahan dari admin, jadwal di sini ikut berubah.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hari & Tanggal</th>
                                <th>Jam</th>
                                @if ($showStudent) <th>Murid</th> @endif
                                <th>Pelajaran</th>
                                <th>Pengajar</th>
                                <th>Ruang</th>
                                @if ($showBranch) <th>Tempat</th> @endif
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr @class(['text-muted' => $session->status === 'cancelled', 'table-primary' => $session->start_at->isToday()])>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $session->start_at->translatedFormat('l') }}{{ $session->start_at->isToday() ? ' · Hari ini' : '' }}</div>
                                        <div class="small text-muted">{{ $session->start_at->translatedFormat('d F Y') }}</div>
                                    </td>
                                    <td class="text-nowrap">{{ $session->timeRange() }}</td>
                                    @if ($showStudent) <td>{{ $session->attendances->pluck('student.name')->join(', ') }}</td> @endif
                                    <td>{{ $session->grade?->label() }}</td>
                                    <td>{{ $session->teacher?->name }}</td>
                                    <td>{{ $session->room?->name }}</td>
                                    @if ($showBranch) <td>@include('portal._branch', ['branch' => $session->branch])</td> @endif
                                    <td>
                                        <span class="badge {{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
                                        @if ($session->moved_at && $session->status === 'scheduled') <div class="small text-warning mt-1"><i class="ri-arrow-left-right-line"></i> Jadwal dipindah</div> @endif
                                        @if ($session->isMakeup()) <div class="small text-info mt-1">Sesi pengganti</div> @endif
                                        @if ($session->cancel_reason) <div class="small mt-1">{{ $session->cancel_reason }}</div> @endif
                                    </td>
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
