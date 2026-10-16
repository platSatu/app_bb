@extends('layouts.dashboard')

@section('title', 'Registrasi Ulang')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-scanners'])

    @if ($new = session('scanner_pin'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1"><i class="ri-key-2-line me-1"></i>PIN untuk {{ $new['name'] }}: <span class="fs-4 ms-1">{{ $new['pin'] }}</span></div>
            <div class="small mb-2">Catat atau kirim ke petugas sekarang. Demi keamanan, PIN <strong>hanya tampil sekali</strong> ini saja.</div>
            <div class="input-group input-group-sm" style="max-width: 640px;">
                <input type="text" class="form-control" value="{{ $new['url'] }}" readonly>
                <button type="button" class="btn btn-outline-dark" data-copy="{{ $new['url'] }}"><i class="ri-file-copy-line"></i> Salin link</button>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-1">
        <div class="col-md-4">
            <div class="card mb-0"><div class="card-body py-3">
                <div class="text-muted small">Sudah hadir</div>
                <div class="fs-4 fw-semibold">{{ $stats['checked_in'] }} <span class="fs-6 text-muted">/ {{ $stats['total'] }} tiket</span></div>
                <div class="progress mt-2" style="height: 6px;"><div class="progress-bar bg-success" style="width: {{ $stats['total'] ? round($stats['checked_in'] / $stats['total'] * 100) : 0 }}%"></div></div>
            </div></div>
        </div>
        <div class="col-md-8">
            <div class="card mb-0"><div class="card-body py-3 d-flex flex-wrap gap-4">
                @forelse ($stats['categories'] as $row)
                    <div><div class="text-muted small">{{ $row['name'] }}</div><div class="fw-semibold">{{ $row['checked_in'] }} / {{ $row['total'] }}</div></div>
                @empty
                    <div class="text-muted small">Belum ada tiket terjual.</div>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Link Petugas Scan</h6>
                    <div class="text-muted small lh-sm">Buat satu link per pintu/petugas. Petugas cukup membuka link dan memasukkan PIN, tanpa perlu akun.</div>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if ($canManage)
                        <form method="POST" action="{{ route($routes.'.ticket.scanners.store', $page) }}" class="d-flex gap-2 mb-3">
                            @csrf
                            <input type="text" name="name" maxlength="100" class="form-control @error('name') is-invalid @enderror" placeholder="mis. Pintu A / Andi" required>
                            <button type="submit" class="btn btn-primary text-nowrap"><i class="ri-add-line"></i> Buat Link</button>
                        </form>
                    @endif

                    @forelse ($scanners as $scanner)
                        <div class="d-flex align-items-center justify-content-between gap-2 border rounded-3 p-2 px-3 mb-2 flex-wrap">
                            <div>
                                <div class="fw-semibold">{{ $scanner->name }} <span class="badge {{ $scanner->statusBadge() }}">{{ $scanner->statusLabel() }}</span></div>
                                <div class="small text-muted">{{ $scanner->last_used_at ? 'Terakhir scan '.$scanner->last_used_at->diffForHumans() : 'Belum pernah dipakai' }}</div>
                            </div>
                            <div class="d-flex gap-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $scanner->url() }}" title="Salin link"><i class="ri-link"></i></button>
                                <a href="{{ $scanner->url() }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="Buka halaman scan"><i class="ri-qr-scan-2-line"></i></a>
                                @if ($canManage)
                                    @foreach (['reset_pin' => ['ri-key-2-line', 'Ganti PIN', 'Ganti PIN '.$scanner->name.'? PIN lama langsung tidak berlaku.'], 'toggle' => [$scanner->isActive() ? 'ri-pause-line' : 'ri-play-line', $scanner->isActive() ? 'Nonaktifkan' : 'Aktifkan', null]] as $action => [$icon, $label, $confirm])
                                        <form method="POST" action="{{ route($routes.'.ticket.scanners.update', [$page, $scanner]) }}" @if ($confirm) onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }});" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="{{ $action }}">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $label }}"><i class="{{ $icon }}"></i></button>
                                        </form>
                                    @endforeach
                                    <form method="POST" action="{{ route($routes.'.ticket.scanners.destroy', [$page, $scanner]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus link '.$scanner->name.'? Riwayat scan tetap tersimpan.') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3">Belum ada link petugas.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Baru Hadir</h6></div>
                <div class="card-body p-3 p-md-4">
                    @forelse ($recent as $ticket)
                        <div class="d-flex justify-content-between border-bottom py-2 small">
                            <div><div class="fw-semibold">{{ $ticket->holder_name }}</div><div class="text-muted">{{ $ticket->category_name }} · {{ $ticket->booking_code }}</div></div>
                            <div class="text-end text-muted">{{ $ticket->checked_in_at->format('H:i') }}<div>{{ $ticket->scanner?->name }}</div></div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-3">Belum ada yang registrasi ulang.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var html = button.innerHTML;
                navigator.clipboard.writeText(button.dataset.copy).then(function () {
                    button.innerHTML = '<i class="ri-check-line"></i>';
                    setTimeout(function () { button.innerHTML = html; }, 1500);
                });
            });
        });
    </script>
@endsection
