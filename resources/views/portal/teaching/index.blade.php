@extends('layouts.portal')

@section('title', 'Jadwal Mengajar')

@php
    $end = $week->copy()->endOfWeek();
    $isThisWeek = $week->isSameDay(today()->startOfWeek());
    $weekUrl = fn ($date) => request()->fullUrlWithQuery(['minggu' => $date?->toDateString()]);
@endphp

@section('content')
    @include('portal._tabs', ['param' => 'branch'])

    <div class="card portal-card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <a href="{{ $weekUrl($week->copy()->subWeek()) }}" class="btn btn-sm btn-light" title="Minggu sebelumnya"><i class="ri-arrow-left-s-line"></i></a>
                <div class="fw-semibold px-1">{{ $week->translatedFormat('d M') }} – {{ $end->translatedFormat('d M Y') }}</div>
                <a href="{{ $weekUrl($week->copy()->addWeek()) }}" class="btn btn-sm btn-light" title="Minggu berikutnya"><i class="ri-arrow-right-s-line"></i></a>
                @unless ($isThisWeek)
                    <a href="{{ $weekUrl(null) }}" class="btn btn-sm btn-outline-primary rounded-pill">Minggu ini</a>
                @endunless
                <span class="ms-auto small text-muted">{{ $sessions->where('status', '!=', 'cancelled')->count() }} sesi</span>
            </div>

            @if ($sessions->contains(fn ($s) => isset($clashes[$s->id])))
                <div class="alert alert-warning d-flex gap-2 py-2">
                    <i class="ri-alert-line fs-5"></i>
                    <div class="small">Ada jadwal yang <strong>waktunya bertabrakan</strong> (ditandai di bawah). Segera kabari admin branch terkait supaya dipindah.</div>
                </div>
            @endif

            @if ($sessions->isEmpty())
                @include('portal._empty', ['icon' => 'ri-cup-line', 'title' => 'Tidak ada jadwal mengajar di minggu ini'])
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hari & Tanggal</th>
                                <th>Jam</th>
                                <th>Pelajaran</th>
                                <th>Murid</th>
                                <th>Ruang</th>
                                @if ($showBranch) <th>Tempat</th> @endif
                                <th>Keterangan</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr @class(['text-muted' => $session->status === 'cancelled', 'table-warning' => isset($clashes[$session->id]), 'table-primary' => ! isset($clashes[$session->id]) && $session->start_at->isToday()])>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $session->start_at->translatedFormat('l') }}{{ $session->start_at->isToday() ? ' · Hari ini' : '' }}</div>
                                        <div class="small text-muted">{{ $session->start_at->translatedFormat('d F Y') }}</div>
                                    </td>
                                    <td class="text-nowrap">{{ $session->timeRange() }}</td>
                                    <td>{{ $session->grade?->label() }}</td>
                                    <td>
                                        {{ $session->attendances->pluck('student.name')->filter()->join(', ') ?: '-' }}
                                        @if ($session->attendances->count() > 1) <div class="small text-muted">{{ $session->attendances->count() }} murid</div> @endif
                                    </td>
                                    <td>{{ $session->room?->name }}</td>
                                    @if ($showBranch) <td>@include('portal._branch', ['branch' => $session->branch])</td> @endif
                                    <td>
                                        <span class="badge {{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
                                        @isset($clashes[$session->id]) <div class="small text-warning fw-semibold mt-1"><i class="ri-alert-line"></i> Bentrok</div> @endisset
                                        @if ($session->isMakeup()) <div class="small text-info mt-1">Sesi pengganti</div> @endif
                                    </td>
                                    <td class="text-end">@include('portal.teaching._action')</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
