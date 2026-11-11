@extends('layouts.dashboard')

@section('title', 'Edit Branch')
@section('page-section', 'Settings')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.branches.update', $branch) }}">
                @csrf
                @method('PUT')
                @include('settings.branch._form')
            </form>
        </div>
    </div>

    {{-- QR tempat les untuk absen pengajar; hanya selama paket Jadwal branch aktif. --}}
    @if ($placeQr)
        <div class="card" id="qr-tempat">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <img src="{{ $placeQr }}" alt="QR tempat {{ $branch->name }}" class="border rounded-3" style="width: 160px; height: 160px; image-rendering: pixelated">
                    <div style="flex: 1 1 240px">
                        <h5 class="mb-1"><i class="ri-qr-code-line text-primary me-1"></i> QR Absen Pengajar</h5>
                        <p class="text-muted small mb-3">Cetak dan tempel QR ini di tempat les. Sebelum mengajar, pengajar scan QR ini dari menu Scan Absen, jam hadirnya langsung tercatat dan kartu murid baru bisa discan setelahnya.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $placeQr }}" download="qr-absen-{{ \Illuminate\Support\Str::slug($branch->name) }}.png" class="btn btn-primary"><i class="ri-download-2-line me-1"></i>Unduh QR</a>
                            <form method="POST" action="{{ route('settings.branches.place-qr', $branch) }}" onsubmit="return confirm('Ganti QR tempat? QR yang sudah tercetak tidak bisa dipakai lagi, jadi perlu dicetak ulang.')">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="btn btn-outline-secondary"><i class="ri-refresh-line me-1"></i>Ganti QR</button>
                            </form>
                        </div>
                        <div class="small text-muted mt-2">Ganti QR kalau QR lama hilang atau dicurigai difoto untuk absen dari luar tempat les.</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Hanya tampil kalau paket WhatsApp branch aktif. Bawaan semua mati. --}}
    @if ($hasWhatsapp)
        <div class="card" id="notifikasi-wa">
            <div class="card-body">
                <h5 class="mb-1"><i class="ri-whatsapp-line text-success me-1"></i> Notifikasi WhatsApp</h5>
                <p class="text-muted small mb-3">Kirim pesan otomatis lewat nomor WhatsApp branch ini, isinya sama dengan email. Setiap pesan memakai kuota paket WhatsApp.</p>

                @unless ($waConnected)
                    <div class="alert alert-warning small py-2">Nomor WhatsApp branch ini belum terhubung, jadi pesan belum bisa dikirim. Hubungkan dulu di menu WhatsApp › Device &amp; Inbox.</div>
                @endunless

                <form method="POST" action="{{ route('settings.branches.wa-notify', $branch) }}">
                    @csrf
                    @method('PUT')
                    @foreach (\App\Models\UserBranch::WA_NOTIFY as $kind => [$label, $hint])
                        <div class="d-flex align-items-center gap-3 py-3 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="flex-grow-1">
                                <label for="wa-{{ $kind }}" class="fw-semibold mb-0">{{ $label }}</label>
                                <div class="small text-muted">{{ $hint }}</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" role="switch" class="form-check-input" style="width: 2.5em; height: 1.3em" id="wa-{{ $kind }}" name="wa_notify[{{ $kind }}]" value="1" @checked(! empty($branch->wa_notify[$kind]))>
                            </div>
                        </div>
                    @endforeach
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success"><i class="ri-save-line me-1"></i>Simpan Notifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
