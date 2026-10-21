@extends('layouts.dashboard')

@section('page-section', 'Student')

@section('title', 'Riwayat Kehadiran')

@use('App\Models\ScheduleAttendance')

@php
    $showBranch = count($colors) > 1;
    $cards = [
        ScheduleAttendance::STATUS_PRESENT => ['Hadir', 'success', 'ri-checkbox-circle-line'],
        ScheduleAttendance::STATUS_EXCUSED => ['Izin', 'warning', 'ri-hand-heart-line'],
        ScheduleAttendance::STATUS_ABSENT => ['Tidak Hadir', 'danger', 'ri-close-circle-line'],
    ];
@endphp

@section('content')
    <div class="pb-4">
    @include('portal._tabs', ['param' => 'anak'])

    <div class="row g-3 mb-3">
        @foreach ($cards as $status => [$label, $color, $icon])
            <div class="col-4">
                <div class="card mb-0">
                    <div class="card-body py-3 text-center">
                        <i class="{{ $icon }} fs-3 text-{{ $color }}"></i>
                        <div class="fs-4 fw-semibold lh-1 my-1">{{ $summary[$status] ?? 0 }}</div>
                        <div class="small text-muted">{{ $label }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                @if ($active) <input type="hidden" name="anak" value="{{ $active }}"> @endif
                <label for="bulan" class="small text-muted mb-0">Bulan</label>
                <input type="month" id="bulan" name="bulan" class="form-control form-control-sm" style="max-width: 180px" value="{{ $month?->format('Y-m') }}" onchange="this.form.submit()">
                @if ($month)
                    <a href="{{ request()->fullUrlWithQuery(['bulan' => null, 'page' => null]) }}" class="btn btn-sm btn-light">Semua bulan</a>
                @endif
            </form>

            @if (! $items || $items->isEmpty())
                @include('portal._empty', ['icon' => 'ri-checkbox-circle-line', 'title' => 'Belum ada catatan kehadiran', 'text' => 'Catatan muncul setelah pengajar mengisi absen di setiap pertemuan.'])
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                @if ($showStudent) <th>Murid</th> @endif
                                <th>Pelajaran</th>
                                <th>Pengajar</th>
                                @if ($showBranch) <th>Tempat</th> @endif
                                <th>Kehadiran</th>
                                <th>Catatan Pengajar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="fw-semibold">{{ $item->session?->start_at->translatedFormat('D, d M Y') }}</div>
                                        <div class="small text-muted">{{ $item->session?->timeRange() }}</div>
                                    </td>
                                    @if ($showStudent) <td>{{ $item->student?->name }}</td> @endif
                                    <td>{{ $item->session?->grade?->label() }}</td>
                                    <td>{{ $item->session?->teacher?->name }}</td>
                                    @if ($showBranch) <td>@include('portal._branch', ['branch' => $item->session?->branch])</td> @endif
                                    <td>
                                        <span class="badge {{ $item->statusBadge() }}">{{ $item->status === ScheduleAttendance::STATUS_ABSENT ? 'Tidak Hadir' : $item->statusLabel() }}</span>
                                        @if ($item->makeup_session_id) <div class="small text-info mt-1">Ada sesi pengganti</div> @endif
                                    </td>
                                    <td class="small text-muted">{{ $item->notes ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
    </div>
@endsection
