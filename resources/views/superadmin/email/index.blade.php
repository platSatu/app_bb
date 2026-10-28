@extends('layouts.dashboard')

@section('title', 'Riwayat Email')
@section('page-section', 'Superadmin')

@section('content')
    @if ($summary['stuck'])
        <div class="alert alert-warning">
            <i class="ri-error-warning-line me-1"></i>{{ $summary['stuck'] }} email tertahan di antrean lebih dari {{ \App\Models\EmailLog::STUCK_MINUTES }} menit. Kemungkinan worker antrean di server mati;
            cek di <a href="{{ route('superadmin.monitor.index') }}" class="alert-link">Antrean &amp; Tugas</a>.
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([['Terkirim (24 jam)', $summary['sent'], 'text-success'], ['Gagal (24 jam)', $summary['failed'], $summary['failed'] ? 'text-danger' : 'text-muted'], ['Tertahan di antrean', $summary['stuck'], $summary['stuck'] ? 'text-warning' : 'text-muted']] as [$label, $value, $color])
            <div class="col-sm-4"><div class="card mb-0 h-100"><div class="card-body">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fs-5 fw-bold {{ $color }}">{{ number_format($value, 0, ',', '.') }}</div>
            </div></div></div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-nowrap align-items-end gap-2 overflow-auto pb-2 mb-3">
                <div class="flex-grow-1" style="min-width: 220px">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Alamat email atau judul" value="{{ request('search') }}">
                </div>
                <div style="min-width: 150px">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach (\App\Models\EmailLog::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 190px">
                    <label class="form-label small text-muted mb-1">Jenis</label>
                    <select name="type" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ \App\Models\EmailLog::TYPE_LABELS[$type] ?? class_basename($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1">Dari tanggal</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1">Sampai tanggal</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="submit" class="btn btn-primary text-nowrap"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'type', 'date_from', 'date_to']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <p class="text-muted small">"Terkirim" = sudah diterima server email; belum tentu dibuka penerima atau tidak masuk spam. Isi email tidak disimpan.</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-nowrap">
                        <tr><th>Waktu</th><th>Jenis</th><th>Tujuan</th><th>Judul</th><th>Status</th><th>Terkirim Pada</th><th>Keterangan</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody class="text-nowrap small">
                        @forelse ($items as $item)
                            <tr>
                                <td class="text-muted">{{ $item->created_at->translatedFormat('d M Y H:i:s') }}</td>
                                <td>{{ $item->typeLabel() }}</td>
                                <td>{{ $item->recipient }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($item->subject, 60) ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span>
                                    @if ($item->isStuck()) <i class="ri-time-line text-warning" title="Tertahan lebih dari {{ \App\Models\EmailLog::STUCK_MINUTES }} menit"></i> @endif
                                </td>
                                <td>{{ $item->sent_at?->translatedFormat('d M Y H:i:s') ?? '-' }}</td>
                                <td class="text-danger" title="{{ $item->error }}">{{ \Illuminate\Support\Str::limit($item->error, 70) }}</td>
                                <td class="text-end">
                                    @if (isset($retryable[$item->job_uuid]))
                                        <form method="POST" action="{{ route('superadmin.emails.resend', $item) }}" onsubmit="this.querySelector('button').disabled = true;">
                                            @csrf <button class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-restart-line"></i> Kirim Ulang</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada email.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
