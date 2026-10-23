{{--
    Daftar link perangkat (token + PIN) untuk satu jenis: registrasi ulang, kiosk cetak mandiri, atau absensi kelas.
    Param: $links, $type, $title, $description, $placeholder, $emptyText, $icon
--}}
@if (($new = session('scanner_pin')) && ($new['type'] ?? \App\Models\TicketScanner::TYPE_CHECKIN) === $type)
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="ri-key-2-line me-1"></i>PIN untuk {{ $new['name'] }}: <span class="fs-4 ms-1">{{ $new['pin'] }}</span></div>
        <div class="small mb-2">Catat atau kirim ke yang bertugas sekarang ya. Demi keamanan, PIN <strong>hanya tampil sekali</strong> ini saja.</div>
        <div class="input-group input-group-sm" style="max-width: 640px;">
            <input type="text" class="form-control" value="{{ $new['url'] }}" readonly>
            <button type="button" class="btn btn-outline-dark" data-copy="{{ $new['url'] }}"><i class="ri-file-copy-line"></i> Salin link</button>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header bg-white border-bottom px-4 py-3">
        <h6 class="mb-1 fw-semibold"><i class="{{ $icon }} me-1 text-primary"></i>{{ $title }}</h6>
        <div class="text-muted small lh-sm">{{ $description }}</div>
    </div>
    <div class="card-body p-3 p-md-4">
        @if ($canManage)
            <form method="POST" action="{{ route($routes.'.ticket.scanners.store', $page) }}" class="d-flex gap-2 mb-3">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="text" name="name" maxlength="100" class="form-control" placeholder="{{ $placeholder }}" required>
                <button type="submit" class="btn btn-primary text-nowrap"><i class="ri-add-line"></i> Buat Link</button>
            </form>
        @endif

        @forelse ($links as $scanner)
            <div class="d-flex align-items-center justify-content-between gap-2 border rounded-3 p-2 px-3 mb-2 flex-wrap">
                <div>
                    <div class="fw-semibold">{{ $scanner->name }} <span class="badge {{ $scanner->statusBadge() }}">{{ $scanner->statusLabel() }}</span></div>
                    <div class="small text-muted">{{ $scanner->last_used_at ? 'Terakhir dipakai '.$scanner->last_used_at->diffForHumans() : 'Belum pernah dipakai' }}</div>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="{{ $scanner->url() }}" title="Salin link"><i class="ri-link"></i></button>
                    <a href="{{ $scanner->url() }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="Buka halamannya"><i class="ri-external-link-line"></i></a>
                    @if ($canManage)
                        @foreach (['reset_pin' => ['ri-key-2-line', 'Ganti PIN', 'Ganti PIN '.$scanner->name.'? PIN lama langsung tidak berlaku.'], 'toggle' => [$scanner->isActive() ? 'ri-pause-line' : 'ri-play-line', $scanner->isActive() ? 'Nonaktifkan' : 'Aktifkan', null]] as $action => [$actionIcon, $label, $confirm])
                            <form method="POST" action="{{ route($routes.'.ticket.scanners.update', [$page, $scanner]) }}" @if ($confirm) onsubmit="return confirm({{ \Illuminate\Support\Js::from($confirm) }});" @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="{{ $action }}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $label }}"><i class="{{ $actionIcon }}"></i></button>
                            </form>
                        @endforeach
                        <form method="POST" action="{{ route($routes.'.ticket.scanners.destroy', [$page, $scanner]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus link '.$scanner->name.'? Riwayatnya tetap tersimpan.') }});">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-3">{{ $emptyText }}</div>
        @endforelse
    </div>
</div>

@once
    @push('scripts')
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
    @endpush
@endonce
