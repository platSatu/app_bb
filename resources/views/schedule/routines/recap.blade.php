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
            <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <input type="month" name="month" class="form-control w-auto" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
                <a href="{{ route($routes.'.routines.recap', ['month' => $month->format('Y-m'), 'export' => 1]) }}" class="btn btn-success"><i class="ri-file-excel-2-line"></i> Unduh Excel</a>
                <a href="{{ route($routes.'.routines.index', ['month' => $month->format('Y-m')]) }}" class="btn btn-outline-secondary">Kembali ke jadwal</a>
            </form>
            <div class="small text-muted mb-3">
                <span class="badge bg-success-subtle text-success">H</span> Hadir ·
                <span class="badge bg-danger-subtle text-danger">A</span> Alpa ·
                <span class="badge bg-warning-subtle text-warning">I</span> Izin ·
                <span class="badge bg-light text-muted">·</span> Belum diisi ·
                <span class="text-muted">–</span> tidak ikut sesi ini.
                Tagihan prorata = harga grade ÷ pertemuan per bulan × sesi yang diikuti (maksimal harga sebulan).
            </div>

            @forelse ($rows as $row)
                @php($routine = $row['routine'])
                <div class="border rounded mb-4">
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
                        <table class="table table-sm align-middle mb-0 text-nowrap small">
                            <thead>
                                <tr>
                                    <th>Murid</th>
                                    <th>Masuk / Keluar</th>
                                    @foreach ($row['sessions'] as $session)
                                        <th class="text-center {{ $session->status === \App\Models\ScheduleSession::STATUS_CANCELLED ? 'text-decoration-line-through text-muted' : '' }}"
                                            title="{{ $session->status === \App\Models\ScheduleSession::STATUS_CANCELLED ? 'Dibatalkan' : '' }}">{{ $session->start_at->format('d/m') }}</th>
                                    @endforeach
                                    <th class="text-center">Diikuti</th>
                                    <th class="text-center">Hadir</th>
                                    <th class="text-end">Tagihan</th>
                                    <th class="text-end">Honor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($row['students'] as $s)
                                    <tr @class(['table-light text-muted' => $s['left_on']])>
                                        <td>{{ $s['student']->name }}</td>
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
                                        <td class="text-end">{{ $rupiah($s['teacher_fee']) }}</td>
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
