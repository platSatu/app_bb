@extends('layouts.dashboard')

@section('title', 'Antrean & Tugas')
@section('page-section', 'Superadmin')

@php
    $schedulerOk = $heartbeat && $heartbeat->gt(now()->subMinutes(3));
    $queueStuck = $jobs->contains('stuck', true);
    $paymentStuck = collect($payments)->sum('count');
    $cards = [
        ['Worker antrean', $queueStuck ? 'Tertahan' : 'Lancar', ! $queueStuck, 'ri-mail-send-line', $queueStuck ? 'Ada job menunggu lebih dari 3 menit. Cek supervisor di server.' : $jobTotal.' job di antrean'],
        ['Jadwal otomatis', $schedulerOk ? 'Berjalan' : 'Tidak jalan', $schedulerOk, 'ri-timer-line', $heartbeat ? 'Terakhir aktif '.$heartbeat->diffForHumans() : 'Belum pernah tercatat. Cek cron di server.'],
        ['Job gagal', $failedTotal ? $failedTotal.' job' : 'Tidak ada', ! $failedTotal, 'ri-error-warning-line', $failedTotal ? 'Bisa dicoba lagi di bawah.' : 'Semua job berhasil.'],
        ['Pembayaran tertahan', $paymentStuck ? $paymentStuck.' transaksi' : 'Tidak ada', ! $paymentStuck, 'ri-bank-card-line', $paymentStuck ? 'Callback Duitku tidak datang.' : 'Semua callback masuk.'],
    ];
@endphp

@section('content')
    <div class="row g-3 mb-1">
        @foreach ($cards as [$title, $value, $ok, $icon, $hint])
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 mb-0 border-{{ $ok ? 'light' : 'warning' }}">
                    <div class="card-body d-flex gap-3">
                        <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 {{ $ok ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}" style="width: 2.75rem; height: 2.75rem;"><i class="{{ $icon }} fs-4"></i></div>
                        <div class="min-w-0">
                            <div class="small text-muted">{{ $title }}</div>
                            <div class="fw-semibold fs-5">{{ $value }}</div>
                            <div class="small text-muted lh-sm">{{ $hint }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-0">
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div><h6 class="mb-1 fw-semibold">Pembayaran tertahan</h6><div class="small text-muted lh-sm">Sudah lewat batas bayar tapi statusnya masih menunggu.</div></div>
                    <form method="POST" action="{{ route('superadmin.monitor.process') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf <button class="btn btn-sm btn-primary" @disabled(! $paymentStuck)><i class="ri-refresh-line me-1"></i>Cek ke Duitku sekarang</button>
                    </form>
                </div>
                <div class="card-body px-4">
                    @foreach ($payments as $row)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div>{{ $row['label'] }} @if ($row['oldest']) <div class="small text-muted">terlama {{ $row['oldest']->diffForHumans() }}</div> @endif</div>
                            <span class="badge {{ $row['count'] ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">{{ $row['count'] ?: 'Aman' }}</span>
                        </div>
                    @endforeach
                    <div class="small text-muted mt-2">Yang ternyata sudah dibayar tetap dilunasi; yang belum dibayar ditandai kedaluwarsa.</div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100 mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-1 fw-semibold">Transfer belum pasti</h6><div class="small text-muted lh-sm">Uang keluar yang masih diproses Duitku atau perlu dicek manual.</div></div>
                <div class="card-body px-4">
                    @foreach ($transfers as $row)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <span>{{ $row['label'] }}</span>
                            @if ($row['count']) <a href="{{ route($row['route']) }}" class="btn btn-sm btn-outline-warning">{{ $row['count'] }} perlu dicek <i class="ri-arrow-right-line"></i></a>
                            @else <span class="badge bg-success-subtle text-success">Aman</span> @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div><h6 class="mb-1 fw-semibold">Job gagal</h6><div class="small text-muted lh-sm">Sudah dicoba beberapa kali dan tetap gagal (mis. server email menolak).</div></div>
            @if ($failedTotal)
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('superadmin.monitor.retry') }}">@csrf <button class="btn btn-sm btn-primary"><i class="ri-restart-line me-1"></i>Coba lagi semua</button></form>
                    <form method="POST" action="{{ route('superadmin.monitor.forget') }}" onsubmit="return confirm('Hapus semua job gagal? Job tidak akan dijalankan lagi.')">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">Hapus semua</button></form>
                </div>
            @endif
        </div>
        <div class="card-body px-4">
            @forelse ($failed as $job)
                <div class="d-flex gap-3 align-items-start py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $job->name }} <span class="small text-muted fw-normal">· gagal {{ $job->failed_at->diffForHumans() }}</span></div>
                        <div class="small text-danger text-break">{{ $job->error }}</div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.monitor.retry') }}">@csrf <input type="hidden" name="uuid" value="{{ $job->uuid }}"><button class="btn btn-sm btn-outline-primary" title="Coba lagi"><i class="ri-restart-line"></i></button></form>
                    <form method="POST" action="{{ route('superadmin.monitor.forget') }}">@csrf @method('DELETE') <input type="hidden" name="uuid" value="{{ $job->uuid }}"><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button></form>
                </div>
            @empty
                <div class="text-center text-muted py-3"><i class="ri-checkbox-circle-line text-success fs-3 d-block"></i>Tidak ada job gagal.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-1 fw-semibold">Antrean saat ini</h6><div class="small text-muted lh-sm">Email dan tugas yang menunggu dikerjakan worker. Normalnya kosong atau cepat habis.</div></div>
        <div class="card-body px-4">
            @forelse ($jobs as $job)
                <div class="d-flex gap-3 align-items-center py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $job->name }} <span class="badge bg-light text-dark border fw-normal">{{ $job->queue }}</span></div>
                        <div class="small {{ $job->stuck ? 'text-warning' : 'text-muted' }}">{{ $job->running ? 'Sedang dikerjakan sejak' : 'Menunggu sejak' }} {{ $job->since->diffForHumans() }} · percobaan ke-{{ $job->attempts }}</div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.monitor.jobs.destroy', $job->id) }}" onsubmit="return confirm('Buang job ini dari antrean?')">@csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger" title="Buang"><i class="ri-delete-bin-line"></i></button></form>
                </div>
            @empty
                <div class="text-center text-muted py-3"><i class="ri-checkbox-circle-line text-success fs-3 d-block"></i>Antrean kosong, semua sudah dikerjakan.</div>
            @endforelse
            @if ($jobTotal > $jobs->count()) <div class="small text-muted mt-2">Menampilkan {{ $jobs->count() }} dari {{ $jobTotal }} job.</div> @endif
        </div>
    </div>
@endsection
