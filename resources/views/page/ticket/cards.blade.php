@extends('layouts.dashboard')

@section('title', 'ID Card')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@push('styles')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <style>
        .size-option { cursor: pointer; border: 2px solid #e9ecef; border-radius: .875rem; padding: .875rem 1rem; transition: border-color .15s, background .15s; height: 100%; }
        .size-option:hover { border-color: #cfd4da; }
        .btn-check:checked + .size-option { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .05); }
        .size-option i { font-size: 1.6rem; }
        .idc-stage { background: repeating-linear-gradient(45deg, #f6f7f9, #f6f7f9 12px, #f1f3f5 12px, #f1f3f5 24px); border-radius: 1rem; padding: 2rem 1rem; display: flex; justify-content: center; }
        .idc-stage-inner { width: 100%; transition: max-width .2s; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); font-weight: 600; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    </style>
@endpush

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-cards'])

    <div class="row g-3 mb-1">
        @foreach ([
            ['Tiket terjual', $total, 'ri-coupon-3-line text-primary'],
            ['ID card sudah dicetak', $printed, 'ri-id-card-line text-success'],
            ['Belum ambil ID card', max(0, $total - $printed), 'ri-time-line text-warning'],
        ] as [$label, $value, $icon])
            <div class="col-md-4">
                <div class="card mb-0">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="{{ $icon }} fs-2"></i>
                        <div><div class="text-muted small">{{ $label }}</div><div class="fs-5 fw-semibold">{{ number_format($value, 0, ',', '.') }}</div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-5">
            <form method="POST" action="{{ route($routes.'.ticket.cards.update', $page) }}" id="card-settings">
                @csrf
                @method('PUT')
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3">
                        <h6 class="mb-1 fw-semibold">Pengaturan Cetak</h6>
                        <div class="text-muted small lh-sm">Peserta menukar e-ticket jadi ID card di kiosk. Kartunya punya QR sendiri, dipakai untuk absensi di setiap kelas.</div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <fieldset @disabled(! $canManage)>
                            <div class="form-check form-switch mb-4 p-3 rounded-3 bg-light ps-5">
                                <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="enabled" @checked(old('enabled', $settings['enabled']))>
                                <label class="form-check-label fw-semibold" for="enabled">Buka cetak ID card di kiosk</label>
                                <div class="small text-muted">Matikan dulu kalau desainnya belum siap. Peserta yang scan akan diminta menunggu.</div>
                            </div>

                            <label class="form-label fw-semibold">Ukuran kertas</label>
                            <div class="row g-2 mb-3">
                                @foreach (\App\Services\Ticket\TicketCardService::SIZES as $key => $size)
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="size" id="size-{{ $key }}" value="{{ $key }}" data-paper="{{ implode('x', $size['mm']) }}" @checked(old('size', $settings['size']) === $key)>
                                        <label class="size-option d-block" for="size-{{ $key }}">
                                            <i class="{{ $key === 'card' ? 'ri-bank-card-line' : 'ri-file-paper-2-line' }} text-primary"></i>
                                            <div class="fw-semibold mt-1">{{ $size['label'] }}</div>
                                            <div class="small text-muted lh-sm">{{ $size['hint'] }}</div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <label class="form-label fw-semibold">Arah kartu</label>
                            <div class="btn-group w-100 mb-3" role="group">
                                @foreach (\App\Services\Ticket\TicketCardService::ORIENTATIONS as $key => $label)
                                    <input type="radio" class="btn-check" name="orientation" id="orientation-{{ $key }}" value="{{ $key }}" @checked(old('orientation', $settings['orientation']) === $key)>
                                    <label class="btn btn-outline-primary" for="orientation-{{ $key }}"><i class="{{ $key === 'portrait' ? 'ri-smartphone-line' : 'ri-tablet-line' }} me-1"></i>{{ $label }}</label>
                                @endforeach
                            </div>

                            <label class="form-label fw-semibold" for="label">Tulisan di kartu</label>
                            <input type="text" name="label" id="label" maxlength="24" class="form-control text-uppercase @error('label') is-invalid @enderror" value="{{ old('label', $settings['label']) }}" placeholder="PESERTA">
                            @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text mb-3">Mis. PESERTA, DELEGASI, atau VISITOR.</div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="show_category" value="1" id="show_category" @checked(old('show_category', $settings['show_category']))>
                                <label class="form-check-label" for="show_category">Tampilkan kategori tiket (mis. VIP, Reguler)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="hole" value="1" id="hole" @checked(old('hole', $settings['hole']))>
                                <label class="form-check-label" for="hole">Tampilkan tanda lubang untuk tali lanyard</label>
                            </div>

                            @if ($canManage)
                                <button type="submit" class="btn btn-primary w-100 mt-4"><i class="ri-save-line me-1"></i>Simpan Pengaturan</button>
                            @endif
                        </fieldset>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-1 fw-semibold">Preview ID Card</h6>
                        <div class="text-muted small lh-sm">Warna mengikuti tab Desain E-ticket, logo dari profil perusahaan.</div>
                    </div>
                    <span class="badge bg-light text-dark" id="paper-size"></span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <div class="idc-stage">
                        <div class="idc-stage-inner" id="idc-stage-inner">
                            @include('page.public._id-card', ['card' => $preview])
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="ri-lightbulb-line me-1"></i>Supaya kartu langsung tercetak tanpa muncul jendela print, buka link kiosk di Google Chrome dengan mode
                        <code>--kiosk --kiosk-printing</code>, dan pilih printer kartu sebagai printer utama (default) di laptop kiosk.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-7">
            @include('page.ticket._scanner-links', [
                'links' => $kiosks,
                'type' => \App\Models\TicketScanner::TYPE_KIOSK,
                'icon' => 'ri-printer-line',
                'title' => 'Kiosk Cetak Mandiri',
                'description' => 'Satu link untuk satu laptop/tablet yang tersambung printer. Buka sekali pakai PIN, lalu biarkan menyala. Peserta tinggal scan e-ticket sendiri.',
                'placeholder' => 'mis. Kiosk Lobby 1',
                'emptyText' => 'Belum ada kiosk. Buat satu dulu ya.',
            ])
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Cara kerjanya</h6></div>
                <div class="card-body p-3 p-md-4">
                    @foreach ([
                        'Peserta membuka e-ticket di HP-nya, lalu mendekatkan QR ke scanner kiosk.',
                        'Kiosk menyapa dengan nama peserta. Peserta memastikan namanya benar, lalu menekan Cetak.',
                        'ID card tercetak dengan QR baru. Pada saat itu juga peserta tercatat sudah registrasi ulang.',
                        'Satu e-ticket hanya bisa jadi satu ID card. Kalau hilang atau rusak, cetak ulang lewat admin atau petugas.',
                    ] as $index => $step)
                        <div class="d-flex gap-2 mb-3 small">
                            <span class="step-num">{{ $index + 1 }}</span>
                            <div class="pt-1">{{ $step }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-white border-bottom px-4 py-3">
            <h6 class="mb-1 fw-semibold">Daftar Peserta</h6>
            <div class="text-muted small lh-sm">Cek siapa saja yang sudah mengambil ID card. Cetak ulang selalu tercatat di riwayat.</div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md"><input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, kode booking, atau nomor tiket" value="{{ request('search') }}"></div>
                <div class="col-12 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua peserta</option>
                        <option value="printed" @selected(request('status') === 'printed')>Sudah punya ID card</option>
                        <option value="waiting" @selected(request('status') === 'waiting')>Belum ambil ID card</option>
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'status'])) <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a> @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Peserta</th><th>No. Tiket</th><th>ID Card</th><th>Registrasi Ulang</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr>
                                <td><div class="fw-semibold">{{ $ticket->holder_name }}</div><div class="text-muted small">{{ $ticket->category_name }}</div></td>
                                <td class="text-nowrap">{{ $ticket->number }}<div class="text-muted small">Kode {{ $ticket->booking_code }}</div></td>
                                <td>
                                    @if ($ticket->hasCard())
                                        <span class="badge bg-success-subtle text-success">Sudah dicetak{{ $ticket->card_print_count > 1 ? ' · '.$ticket->card_print_count.'x' : '' }}</span>
                                        <div class="text-muted small">{{ $ticket->card_printed_at->translatedFormat('d M H:i') }}</div>
                                    @else
                                        <span class="text-muted small">Belum</span>
                                    @endif
                                </td>
                                <td>{!! $ticket->isCheckedIn() ? '<span class="badge bg-success-subtle text-success">Hadir '.e($ticket->checked_in_at->translatedFormat('d M H:i')).'</span>' : '<span class="text-muted small">Belum</span>' !!}</td>
                                <td class="text-end">
                                    @if ($canManage)
                                        <button type="button" class="btn btn-sm rounded-pill {{ $ticket->hasCard() ? 'btn-outline-secondary' : 'btn-outline-primary' }}"
                                            data-print-url="{{ route($routes.'.ticket.cards.print', [$page, $ticket]) }}"
                                            data-print-name="{{ $ticket->holder_name }}" data-print-again="{{ $ticket->hasCard() ? 1 : 0 }}">
                                            <i class="ri-printer-line"></i> {{ $ticket->hasCard() ? 'Cetak ulang' : 'Cetak' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'status']) ? 'Tidak ada peserta yang cocok.' : 'Belum ada tiket terjual. Peserta akan muncul di sini setelah membayar.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $tickets->links() }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Riwayat Cetak Terakhir</h6></div>
        <div class="card-body p-3 p-md-4">
            @forelse ($logs as $log)
                <div class="d-flex justify-content-between border-bottom py-2 small gap-3">
                    <div>
                        <div class="fw-semibold">{{ $log->ticket?->holder_name ?? 'Tiket terhapus' }}</div>
                        <div class="text-muted">{{ $log->actionLabel() }} · {{ $log->sourceLabel() }}{{ $log->scanner ? ' '.$log->scanner->name : '' }}{{ $log->actor ? ' ('.$log->actor->name.')' : '' }}</div>
                    </div>
                    <div class="text-end text-muted text-nowrap">{{ $log->created_at->translatedFormat('d M H:i') }}</div>
                </div>
            @empty
                <div class="text-center text-muted py-3">Belum ada ID card yang dicetak.</div>
            @endforelse
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade" id="print-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" target="_blank" class="modal-content" id="print-form">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-semibold" id="print-title">Cetak ID card</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" id="print-text"></p>
                        <div id="print-options">
                            <div class="form-check border rounded-3 p-3 ps-5 mb-2">
                                <input class="form-check-input" type="radio" name="replace" value="0" id="replace-0" checked>
                                <label class="form-check-label" for="replace-0"><span class="fw-semibold">Kartunya rusak / kurang jelas</span><div class="small text-muted">QR tetap sama. Kartu lama masih bisa dipakai.</div></label>
                            </div>
                            <div class="form-check border rounded-3 p-3 ps-5">
                                <input class="form-check-input" type="radio" name="replace" value="1" id="replace-1">
                                <label class="form-check-label" for="replace-1"><span class="fw-semibold">Kartunya hilang</span><div class="small text-muted">Dibuatkan QR baru. Kartu lama otomatis tidak bisa dipakai absen lagi.</div></label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="ri-printer-line me-1"></i>Cetak Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            // Preview ikut berubah saat pengaturan diubah, sebelum disimpan.
            var form = document.getElementById('card-settings');
            var card = document.querySelector('[data-idc]');
            var stage = document.getElementById('idc-stage-inner');
            function refresh() {
                var size = form.querySelector('[name=size]:checked'), landscape = form.querySelector('[name=orientation]:checked').value === 'landscape';
                var mm = size.dataset.paper.split('x').map(Number), w = landscape ? mm[1] : mm[0], h = landscape ? mm[0] : mm[1];
                card.style.setProperty('--ratio', w + ' / ' + h);
                card.style.setProperty('--k', landscape ? (h / w).toFixed(4) : 1);
                card.classList.toggle('is-landscape', landscape);
                card.classList.toggle('is-card', size.value === 'card');
                card.classList.toggle('is-a5', size.value === 'a5');
                card.classList.toggle('has-hole', form.querySelector('#hole').checked);
                card.querySelector('[data-idc-cat]').hidden = !form.querySelector('#show_category').checked;
                card.querySelector('[data-idc-label]').textContent = (form.querySelector('#label').value || 'PESERTA').toUpperCase();
                stage.style.maxWidth = landscape ? '460px' : '290px';
                document.getElementById('paper-size').textContent = String(w).replace('.', ',') + ' × ' + String(h).replace('.', ',') + ' mm';
            }
            form.addEventListener('input', refresh);
            form.addEventListener('change', refresh);
            refresh();

            var modalEl = document.getElementById('print-modal');
            if (!modalEl) return;
            var modal = new bootstrap.Modal(modalEl), printForm = document.getElementById('print-form');
            document.querySelectorAll('[data-print-url]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var again = button.dataset.printAgain === '1';
                    printForm.action = button.dataset.printUrl;
                    document.getElementById('print-title').textContent = again ? 'Cetak ulang ID card' : 'Cetak ID card';
                    document.getElementById('print-text').textContent = again
                        ? button.dataset.printName + ' sudah pernah mencetak ID card. Kenapa perlu dicetak ulang?'
                        : 'ID card untuk ' + button.dataset.printName + ' akan dibuka di tab baru, siap dicetak.';
                    document.getElementById('print-options').hidden = !again;
                    document.getElementById('replace-0').checked = true;
                    modal.show();
                });
            });
            // Kartu terbuka di tab baru; halaman ini dimuat ulang supaya statusnya ikut terbaru.
            printForm.addEventListener('submit', function () { modal.hide(); setTimeout(function () { location.reload(); }, 1500); });
        })();
    </script>
@endpush
