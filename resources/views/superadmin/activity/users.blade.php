@extends('layouts.dashboard')

@section('title', 'Aktivitas & Pengunjung')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.activity._tabs', ['active' => 'users'])

    @include('superadmin.activity._cards', ['cards' => [
        ['Pengguna aktif hari ini', number_format($activeToday, 0, ',', '.'), 'ri-user-follow-line', 'primary', 'Login dan memakai aplikasi'],
        ['Pengguna aktif 7 hari', number_format($activeWeek, 0, ',', '.'), 'ri-team-line', 'success', 'Minimal sekali dalam seminggu'],
        ['Aktivitas hari ini', number_format($actionsToday, 0, ',', '.'), 'ri-cursor-line', 'warning', 'Halaman dibuka dan aksi'],
        ['Disimpan selama', \App\Models\UserActivityLog::KEEP_DAYS.' hari', 'ri-shield-check-line', 'info', 'Isi form tidak pernah dicatat'],
    ]])

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card">
                <div class="card-body">
                    @include('shared.filters', [
                        'placeholder' => 'Nama / email pengguna',
                        'selectLabel' => 'Jenis',
                        'selectName' => 'type',
                        'selectOptions' => \App\Models\UserActivityLog::TYPE_LABELS,
                    ])

                    @if (request('user'))
                        <div class="alert alert-info py-2 small d-flex justify-content-between align-items-center">
                            <span>Menampilkan aktivitas satu pengguna.</span>
                            <a href="{{ route('superadmin.activity.users', request()->except('user', 'page')) }}">Tampilkan semua</a>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Perangkat</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($logs as $log)
                                    <tr>
                                        <td class="text-nowrap">{{ $log->created_at->translatedFormat('d M Y') }}<div class="text-muted small">{{ $log->created_at->format('H:i:s') }}</div></td>
                                        <td>
                                            @if ($log->user)
                                                <a href="{{ route('superadmin.activity.users', ['user' => $log->user_id]) }}" class="fw-medium">{{ $log->user->name }}</a>
                                                <div class="text-muted small">{{ $log->user->email }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->method === 'GET' ? 'bg-light text-dark' : 'bg-primary-subtle text-primary' }}">{{ $log->verb() }}</span>
                                            {{ $log->menuLabel() }}
                                            @if ($log->status >= 400)
                                                <span class="badge bg-danger-subtle text-danger">gagal ({{ $log->status }})</span>
                                            @endif
                                            <div class="text-muted small text-break">{{ $log->path }}</div>
                                        </td>
                                        <td class="small text-nowrap">
                                            {{ collect([$log->browser, $log->os])->filter()->implode(' · ') ?: '-' }}
                                            <div class="text-muted">{{ $log->ip_address }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada aktivitas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $logs->links() }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-1">Menu paling sering dibuka</h6>
                    <p class="small text-muted mb-3">7 hari terakhir</p>
                    @forelse ($topMenus as $menu => $count)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small gap-2"><span>{{ $menu }}</span><span class="fw-semibold">{{ number_format($count, 0, ',', '.') }}</span></div>
                            <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ round($count / max(1, $topMenus->max()) * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada data.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
