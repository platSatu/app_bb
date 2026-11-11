@extends('layouts.dashboard')

@section('title', 'Rekap Jadwal '.$month->translatedFormat('F Y'))
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

{{-- Arsip bulanan: siapa ikut kelas apa (tanggal masuk/keluar), kehadiran per tanggal, tagihan prorata & honor. --}}
@php
    $codes = [
        \App\Models\ScheduleAttendance::STATUS_PRESENT => ['H', 'bg-success-subtle text-success'],
        \App\Models\ScheduleAttendance::STATUS_ABSENT => ['A', 'bg-danger-subtle text-danger'],
        \App\Models\ScheduleAttendance::STATUS_EXCUSED => ['I', 'bg-warning-subtle text-warning'],
        \App\Models\ScheduleAttendance::STATUS_PENDING => ['·', 'bg-light text-muted'],
    ];
    $rupiah = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                <form method="GET" class="d-flex align-items-center gap-2 me-auto">
                    <label for="month" class="text-muted small text-nowrap mb-0">Bulan</label>
                    <input type="month" id="month" name="month" class="form-control" style="width: 11rem" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                </form>
                <a href="{{ route($routes.'.routines.index', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-secondary"><i class="ri-arrow-left-line me-1"></i>Kembali ke jadwal</a>
                <a href="{{ route($routes.'.routines.recap', ['month' => $month->format('Y-m'), 'export' => 1]) }}" class="btn btn-success"><i class="ri-file-excel-2-line me-1"></i>Unduh Excel</a>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-3 small text-muted mb-2">
                <span><span class="badge bg-success-subtle text-success me-1">H</span>Hadir</span>
                <span><span class="badge bg-danger-subtle text-danger me-1">A</span>Alpa</span>
                <span><span class="badge bg-warning-subtle text-warning me-1">I</span>Izin</span>
                <span><span class="badge bg-light text-muted me-1">·</span>Belum diisi</span>
                <span><span class="me-1">–</span>Tidak ikut sesi ini</span>
            </div>
            <p class="small text-muted mb-4">Tagihan dihitung sesuai sesi yang diikuti: harga grade ÷ pertemuan sebulan × sesi diikuti (paling banyak harga sebulan).</p>

            @forelse ($rows as $row)
                @php($routine = $row['routine'])
                <div class="border rounded-3 overflow-hidden mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-2 px-3 py-2 bg-light-subtle border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $routine->grade?->label() }}</div>
                            <div class="small text-muted">{{ $routine->dayLabel() }} {{ $routine->timeRange() }} · {{ $routine->teacher?->name ?? '-' }}</div>
                        </div>
                        <div class="small text-muted text-end">
                            {{ $row['students']->count() }} peserta · Honor {{ $rupiah($row['students']->sum('teacher_fee')) }}
                            <div>Tagihan {{ $rupiah($row['students']->sum('charge')) }}</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        {{-- Lebar kolom tetap supaya kolom Murid s/d Honor sejajar di semua jadwal. --}}
                        <table class="table table-sm align-middle mb-0 text-nowrap small" style="table-layout: fixed; min-width: 760px">
                            <colgroup>
                                <col style="width: 18%"><col style="width: 15%">
                                @foreach ($row['sessions'] as $session) <col> @endforeach
                                <col style="width: 8%"><col style="width: 7%"><col style="width: 12%"><col style="width: 12%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="ps-3">Murid</th>
                                    <th>Masuk / Keluar</th>
                                    @foreach ($row['sessions'] as $session)
                                        <th class="text-center {{ $session->status === \App\Models\ScheduleSession::STATUS_CANCELLED ? 'text-decoration-line-through text-muted' : '' }}"
                                            title="{{ $session->status === \App\Models\ScheduleSession::STATUS_CANCELLED ? 'Dibatalkan' : '' }}">{{ $session->start_at->format('d/m') }}</th>
                                    @endforeach
                                    <th class="text-center">Diikuti</th>
                                    <th class="text-center">Hadir</th>
                                    <th class="text-end">Tagihan</th>
                                    <th class="text-end pe-3">Honor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($row['students'] as $s)
                                    <tr @class(['table-light text-muted' => $s['left_on']])>
                                        <td class="ps-3 text-truncate">{{ $s['student']->name }}</td>
                                        <td>
                                            {{ $s['joined_on'] ? \Carbon\Carbon::parse($s['joined_on'])->translatedFormat('d M') : 'awal' }}
                                            @if ($s['left_on']) – <span class="text-danger">keluar {{ \Carbon\Carbon::parse($s['left_on'])->translatedFormat('d M') }}</span> @endif
                                        </td>
                                        @foreach ($s['marks'] as $mark)
                                            <td class="text-center">
                                                @if ($mark)
                                                    @php([$code, $badge] = $codes[$mark->status] ?? ['?', 'bg-light'])
                                                    <span class="badge {{ $badge }}" title="{{ $mark->statusLabel() }}">{{ $code }}</span>
                                                @else
                                                    <span class="text-muted">–</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-center">{{ $s['followed'] }}</td>
                                        <td class="text-center">{{ $s['present'] }}</td>
                                        <td class="text-end">{{ $rupiah($s['charge']) }}</td>
                                        <td class="text-end pe-3">{{ $rupiah($s['teacher_fee']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">Belum ada sesi di {{ $month->translatedFormat('F Y') }}.</div>
            @endforelse
        </div>
    </div>
@endsection
