{{-- Link pendaftaran customer Invoice per branch. Yang daftar lewat link: dibuatkan akun + otomatis masuk ke daftar customer ini. --}}
<div class="modal fade" id="register-link-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-link me-1"></i> Link Pendaftaran Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Bagikan link ini ke customer. Mereka membuat akun (verifikasi email seperti biasa), otomatis masuk ke daftar customer Invoice cabang ini, lalu langsung melihat tagihannya di menu <strong>Tagihan Saya</strong>.</p>
                @foreach ($branches as $branch)
                    @php $link = $links->get($branch->id); @endphp
                    <div class="border rounded p-3 {{ $loop->last ? '' : 'mb-2' }}">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <div class="fw-semibold"><i class="ri-store-2-line me-1"></i>{{ $branch->name }}</div>
                            @if ($link)
                                <span class="badge {{ $link->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $link->is_active ? 'Aktif' : 'Ditutup' }}</span>
                            @endif
                        </div>
                        @if ($link)
                            <div class="input-group input-group-sm mb-2">
                                <input type="text" class="form-control" value="{{ $link->url() }}" readonly id="register-link-{{ $branch->id }}">
                                <button type="button" class="btn btn-light border" data-copy-link="register-link-{{ $branch->id }}" title="Salin"><i class="ri-file-copy-line"></i> Salin</button>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" action="{{ route('invoices.register-links.toggle', $branch->id) }}">@csrf
                                    <button class="btn btn-sm btn-light">{!! $link->is_active ? '<i class="ri-lock-line"></i> Tutup Link' : '<i class="ri-lock-unlock-line"></i> Buka Link' !!}</button>
                                </form>
                                <form method="POST" action="{{ route('invoices.register-links.store', $branch->id) }}" onsubmit="return confirm('Ganti link? Link lama langsung tidak bisa dipakai.')">@csrf
                                    <button class="btn btn-sm btn-light"><i class="ri-refresh-line"></i> Ganti Link</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('invoices.register-links.store', $branch->id) }}">@csrf
                                <button class="btn btn-sm btn-primary"><i class="ri-add-line"></i> Buat Link</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('[data-copy-link]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.copyLink);
            input.select();
            (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
                button.innerHTML = '<i class="ri-check-line"></i> Tersalin';
                setTimeout(function () { button.innerHTML = '<i class="ri-file-copy-line"></i> Salin'; }, 1500);
            });
        });
    });
</script>
