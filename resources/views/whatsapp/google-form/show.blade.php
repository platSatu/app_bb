@extends('layouts.dashboard')

@section('title', $integration->name)
@section('page-section', 'WhatsApp')

@php
    $tab = in_array(request('tab'), ['script', 'registrasi'], true) ? request('tab') : 'pendaftar';
    $device = $integration->device;
    $tabUrl = fn ($key) => route('whatsapp.google-forms.show', [$integration, 'tab' => $key]);
    $tabs = ['pendaftar' => ['Pendaftar', 'ri-group-line', $tabUrl('pendaftar')], 'script' => ['Pasang Script', 'ri-code-s-slash-line', $tabUrl('script')]];
    if ($integration->registration_enabled) {
        $tabs['registrasi'] = ['Aplikasi Registrasi Ulang', 'ri-qr-scan-2-line', $tabUrl('registrasi')];
    }
    $cards = array_filter([
        ['Daftar', $stats['daftar'], 'ri-user-add-line', 'primary'],
        ['Pesan terkirim', $stats['terkirim'], 'ri-whatsapp-line', 'success'],
        $stats['lunas'] !== null ? ['Sudah bayar', $stats['lunas'], 'ri-bank-card-line', 'warning'] : null,
        $stats['hadir'] !== null ? ['Hadir', $stats['hadir'], 'ri-checkbox-circle-line', 'info'] : null,
    ]);
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <a href="{{ route('whatsapp.google-forms.index', ['branch' => $integration->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke Google Form</a>
        <div class="d-flex gap-2">
            <a href="{{ route('whatsapp.google-forms.export', $integration) }}" class="btn btn-sm btn-outline-success"><i class="ri-file-excel-2-line"></i> Export Excel</a>
            <a href="{{ route('whatsapp.google-forms.edit', $integration) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-settings-3-line"></i> Pengaturan</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1 min-w-0">
                <h5 class="mb-1">{{ $integration->name }}</h5>
                <div class="small text-muted">
                    Dikirim dari {{ $device?->name ?? '-' }} {{ $device?->phone ? '(+'.$device->phone.')' : '' }}
                    @if ($integration->payment_enabled) · bayar Rp {{ number_format($integration->amount, 0, ',', '.') }} @endif
                    @if ($integration->registration_enabled) · registrasi ulang aktif @endif
                </div>
            </div>
            <span class="badge {{ $integration->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} fs-6">{{ $integration->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        @if ($device && ! $device->isConnected())
            <div class="alert alert-warning mx-3 mb-3"><i class="ri-error-warning-line me-1"></i>Nomor pengirim sedang tidak terhubung. Form tetap tercatat, tapi pesannya gagal sampai nomornya dihubungkan lagi.</div>
        @endif
    </div>

    <div class="row g-3 mb-3">
        @foreach ($cards as [$label, $value, $icon, $color])
            <div class="col-6 col-lg">
                <div class="card mb-0 h-100"><div class="card-body d-flex align-items-center gap-3">
                    <span class="rounded-3 bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.5rem; height: 2.5rem;"><i class="{{ $icon }} fs-5"></i></span>
                    <div><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-semibold lh-1">{{ number_format($value) }}</div></div>
                </div></div>
            </div>
        @endforeach
    </div>

    @include('whatsapp._tabs', ['tabs' => $tabs, 'active' => $tab])

    @if ($tab === 'pendaftar')
        <div class="card">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 420px;">
                    <input type="hidden" name="tab" value="pendaftar">
                    <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari nama, nomor, atau kode" value="{{ request('cari') }}">
                    <button class="btn btn-sm btn-outline-secondary"><i class="ri-search-line"></i></button>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pendaftar</th><th>Waktu</th><th>Pesan</th>
                                @if ($integration->payment_enabled) <th>Pembayaran</th> @endif
                                @if ($integration->registration_enabled) <th>Kode</th><th>Kehadiran</th><th class="text-end">Aksi</th> @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($submissions as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row->displayName() }}</div>
                                        <div class="small text-muted">{{ $row->phone ? '+'.$row->phone : 'Nomor tidak ditemukan' }}</div>
                                        <details class="small"><summary class="text-muted" style="cursor: pointer;">Jawaban</summary>
                                            @foreach ($row->payload as $question => $answer)
                                                <div><span class="text-muted">{{ $question }}:</span> {{ $answer !== '' ? $answer : '-' }}</div>
                                            @endforeach
                                        </details>
                                    </td>
                                    <td class="small text-nowrap">{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $row->status === 'sent' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $row->status === 'sent' ? 'Terkirim' : 'Gagal' }}</span>
                                        @if ($row->error) <div class="small text-danger" style="max-width: 260px;">{{ $row->error }}</div> @endif
                                    </td>
                                    @if ($integration->payment_enabled)
                                        <td>@if ($row->invoice_id) <span class="badge {{ $row->isPaid() ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $row->paymentLabel() }}</span> @else <span class="text-muted">-</span> @endif</td>
                                    @endif
                                    @if ($integration->registration_enabled)
                                        <td class="font-monospace small">{{ $row->code ?? '-' }}</td>
                                        <td class="small text-nowrap">{!! $row->attended_at ? '<span class="text-success"><i class="ri-checkbox-circle-fill"></i> '.e($row->attended_at->translatedFormat('d M H:i')).'</span>' : '<span class="text-muted">Belum</span>' !!}</td>
                                        <td class="text-end text-nowrap">
                                            @if ($row->code && $row->isPaid())
                                                <div class="d-inline-flex flex-nowrap gap-1">
                                                    <a href="{{ $row->ticketUrl() }}" target="_blank" rel="noopener" class="btn btn-sm rounded-pill btn-outline-secondary" title="Lihat QR"><i class="ri-qr-code-line"></i></a>
                                                    <form method="POST" class="m-0" action="{{ route('whatsapp.google-forms.resend', [$integration, $row]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Kirim ulang QR ke '.$row->displayName().'?') }})">
                                                        @csrf
                                                        <button class="btn btn-sm rounded-pill btn-outline-primary text-nowrap" title="Kirim ulang QR lewat WhatsApp"><i class="ri-send-plane-line"></i> Kirim QR</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4"><i class="ri-inbox-line fs-2 d-block mb-1"></i>{{ request('cari') ? 'Tidak ada pendaftar yang cocok.' : 'Belum ada pendaftar. Setelah script terpasang, coba isi form Anda sekali.' }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $submissions->links() }}</div>
            </div>
        </div>
    @elseif ($tab === 'script')
        @include('whatsapp.google-form._script')
    @else
        <div class="card">
            <div class="card-body px-4">
                <h6 class="fw-semibold">Aplikasi registrasi ulang untuk petugas</h6>
                <p class="small text-muted">Buka link ini di HP/laptop petugas di lokasi acara, masukkan PIN yang Anda atur di Pengaturan, lalu scan QR pendaftar (atau ketik kodenya). Satu QR hanya bisa dipakai sekali.</p>
                <div class="input-group mb-2" style="max-width: 620px;">
                    <input type="text" class="form-control font-monospace" value="{{ $integration->checkinUrl() }}" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy="{{ $integration->checkinUrl() }}"><i class="ri-file-copy-line"></i></button>
                    <a href="{{ $integration->checkinUrl() }}" target="_blank" rel="noopener" class="btn btn-primary">Buka</a>
                </div>
                <div class="small text-muted"><i class="ri-shield-keyhole-line"></i> Link saja tidak cukup untuk masuk, petugas tetap perlu PIN. Ganti PIN di Pengaturan kapan saja.</div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
