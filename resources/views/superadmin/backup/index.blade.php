@extends('layouts.dashboard')

@section('title', 'Backup Database')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h6 class="mb-1 fw-semibold">Backup Database</h6>
                <div class="text-muted small lh-sm">Backup otomatis setiap hari pukul 01.00. Disimpan {{ $keep }} backup terbaru; yang lebih lama terhapus sendiri.</div>
            </div>
            <form method="POST" action="{{ route('superadmin.backups.store') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-1&quot;></span>Sedang membuat backup...';">
                @csrf
                <button type="submit" class="btn btn-primary"><i class="ri-database-2-line me-1"></i>Backup Sekarang</button>
            </form>
        </div>
        <div class="card-body p-3 p-md-4">
            @forelse ($backups as $backup)
                <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                    <div class="avatar-sm flex-shrink-0 rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;"><i class="ri-file-zip-line fs-5"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $backup['name'] }}</div>
                        <div class="small text-muted">
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($backup['time'], config('app.timezone'))->translatedFormat('l, d F Y H:i') }}
                            · {{ \Illuminate\Support\Number::fileSize($backup['size'], 1) }}
                            @if ($loop->first) <span class="badge bg-success-subtle text-success ms-1">Terbaru</span> @endif
                        </div>
                    </div>
                    <a href="{{ route('superadmin.backups.download', $backup['name']) }}" class="btn btn-sm btn-outline-primary"><i class="ri-download-2-line me-1"></i>Download</a>
                </div>
            @empty
                <div class="text-center text-muted py-4">
                    <i class="ri-database-2-line fs-1 d-block mb-2"></i>
                    Belum ada backup. Klik <b>Backup Sekarang</b> untuk membuat yang pertama.
                </div>
            @endforelse
        </div>
    </div>

    <div class="alert alert-light border small">
        <i class="ri-shield-keyhole-line me-1"></i>File backup berisi seluruh data (user, saldo, invoice). Simpan hasil download di tempat aman dan jangan dibagikan.
        Untuk memulihkan: <code>gunzip -c nama-file.sql.gz | mysql -u USER -p NAMA_DATABASE</code>
    </div>
@endsection
