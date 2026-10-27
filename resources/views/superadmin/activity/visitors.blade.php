@extends('layouts.dashboard')

@section('title', 'Aktivitas & Pengunjung')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.activity._tabs', ['active' => 'visitors'])

    @php
        $n = fn ($v) => number_format($v, 0, ',', '.');
        $period = $from->translatedFormat('d M Y').' – '.$until->translatedFormat('d M Y');
        $maxDay = max(1, $daily->max('visitors'));
        $deviceLabels = ['mobile' => 'HP', 'desktop' => 'Komputer', 'tablet' => 'Tablet'];
    @endphp

    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1">Dari tanggal</label>
            <input type="date" name="date_from" class="form-control" value="{{ $from->toDateString() }}">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small text-muted mb-1">Sampai tanggal</label>
            <input type="date" name="date_to" class="form-control" value="{{ $until->toDateString() }}">
        </div>
        <div class="col-12 col-md-3 col-xl-2">
            <button type="submit" class="btn btn-primary w-100"><i class="ri-search-line"></i> Tampilkan</button>
        </div>
    </form>

    @include('superadmin.activity._cards', ['cards' => [
        ['Pengunjung', $n($total['visitors']), 'ri-user-3-line', 'primary', 'Orang berbeda · '.$period],
        ['Halaman dibuka', $n($total['views']), 'ri-file-list-3-line', 'success', 'Semua halaman · '.$period],
        ['Halaman per pengunjung', $total['visitors'] ? number_format($total['views'] / $total['visitors'], 1, ',', '.') : '0', 'ri-stack-line', 'warning', 'Rata-rata halaman yang dibuka'],
        ['Pengunjung hari ini', $n($today), 'ri-time-line', 'info', 'Sejak pukul 00.00'],
    ]])

    <div class="card">
        <div class="card-body">
            <h6 class="mb-1">Pengunjung per hari</h6>
            <p class="small text-muted mb-3">Arahkan kursor ke batang untuk melihat angkanya.</p>
            <div class="d-flex align-items-end gap-1 overflow-auto pb-2" style="height:180px">
                @foreach ($daily as $day)
                    <div class="d-flex flex-column align-items-center justify-content-end h-100 flex-fill" style="min-width:14px" title="{{ $day['label'] }}: {{ $day['visitors'] }} pengunjung, {{ $day['views'] }} halaman">
                        <div class="w-100 rounded-top bg-primary" style="height:{{ max(2, round($day['visitors'] / $maxDay * 150)) }}px; opacity:{{ $day['visitors'] ? 1 : .2 }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex justify-content-between small text-muted">
                <span>{{ $daily->first()['label'] ?? '' }}</span><span>{{ $daily->last()['label'] ?? '' }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">Halaman terpopuler</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Halaman</th><th class="text-end">Dibuka</th><th class="text-end">Pengunjung</th></tr></thead>
                            <tbody>
                                @forelse ($pages as $page)
                                    <tr><td class="text-break">{{ $page->path === '/' ? 'Beranda (/)' : $page->path }}</td><td class="text-end">{{ $n($page->views) }}</td><td class="text-end">{{ $n($page->visitors) }}</td></tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada kunjungan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">Asal pengunjung</h6>
                    @forelse ($sources as $source => $count)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span>{{ $source }}</span><span class="fw-semibold">{{ $n($count) }}</span></div>
                            <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ round($count / max(1, $sources->max()) * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada data.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">Perangkat</h6>
                    @forelse ($devices as $device => $count)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span>{{ $deviceLabels[$device] ?? 'Lainnya' }}</span><span class="fw-semibold">{{ round($count / max(1, $devices->sum()) * 100) }}%</span></div>
                            <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:{{ round($count / max(1, $devices->sum()) * 100) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum ada data.</p>
                    @endforelse
                    @if ($bots)
                        <p class="small text-muted mt-3 mb-0">{{ $n($bots) }} kunjungan bot (mesin pencari dan sejenisnya) tidak dihitung.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
