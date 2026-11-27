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
                        @if ($branch->place_qr_grace_until?->isFuture())
                            <div class="small text-warning-emphasis mt-1"><i class="ri-time-line"></i> QR baru saja diganti otomatis. QR lama masih bisa dipakai sampai {{ $branch->place_qr_grace_until->translatedFormat('d M Y H:i') }}, segera cetak yang baru ya.</div>
                        @endif
                    </div>
                </div>

                {{-- Pengaman QR tempat: cek lokasi GPS & ganti QR otomatis tiap awal bulan. --}}
                <form method="POST" action="{{ route('settings.branches.place-location', $branch) }}" class="border-top mt-4 pt-3" data-place-location>
                    @csrf
                    @method('PUT')
                    <h6 class="mb-1"><i class="ri-map-pin-line text-primary me-1"></i> Pastikan pengajar benar-benar di lokasi</h6>
                    <p class="small text-muted mb-3">
                        Isi titik lokasi tempat les. Saat pengajar scan QR tempat, lokasi HP-nya dicek dan absen ditolak kalau lebih dari
                        {{ \App\Models\UserBranch::PLACE_RADIUS_METERS }} meter dari titik ini. Paling mudah: buka halaman ini di HP saat Anda berada di tempat les, lalu tekan <strong>Pakai lokasi saya sekarang</strong>.
                    </p>
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-3">
                            <label for="latitude" class="form-label small mb-1">Garis lintang</label>
                            <input type="text" inputmode="decimal" id="latitude" name="latitude" class="form-control form-control-sm @error('latitude') is-invalid @enderror" value="{{ old('latitude', $branch->latitude) }}" placeholder="-6.2000000">
                            @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="longitude" class="form-label small mb-1">Garis bujur</label>
                            <input type="text" inputmode="decimal" id="longitude" name="longitude" class="form-control form-control-sm @error('longitude') is-invalid @enderror" value="{{ old('longitude', $branch->longitude) }}" placeholder="106.8000000">
                            @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-use-location><i class="ri-focus-3-line me-1"></i>Pakai lokasi saya sekarang</button>
                        </div>
                    </div>
                    <div class="small mt-1" data-location-message>
                        @unless ($branch->hasPlaceLocation())
                            <span class="text-muted"><i class="ri-information-line"></i> Belum diisi: scan QR tempat belum dicek lokasinya.</span>
                        @endunless
                    </div>

                    <div class="d-flex align-items-center gap-3 mt-3">
                        <div class="flex-grow-1">
                            <label for="place-qr-auto" class="fw-semibold small mb-0">Ganti QR otomatis tiap awal bulan</label>
                            <div class="small text-muted">QR lama masih berlaku {{ \App\Models\UserBranch::PLACE_QR_GRACE_DAYS }} hari supaya Anda sempat mencetak ulang. Pengingatnya dikirim ke WhatsApp Anda kalau chatbot WhatsApp Jadwal aktif.</div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox" role="switch" class="form-check-input" style="width: 2.5em; height: 1.3em" id="place-qr-auto" name="place_qr_auto_rotate" value="1" @checked(old('place_qr_auto_rotate', $branch->place_qr_auto_rotate))>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="ri-save-line me-1"></i>Simpan</button>
                    </div>
                </form>
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
                    @if ($hasSchedules)
                        {{-- Notifikasi Jadwal (murid, pengajar, admin) punya halaman sendiri. --}}
                        <div class="d-flex align-items-center gap-3 py-3 border-top">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">Jadwal</div>
                                <div class="small text-muted">Pengingat les ke murid, rekap & info perubahan ke pengajar, kabar absen. Diatur di menu Schedules.</div>
                            </div>
                            <a href="{{ route('schedules.notifications.index') }}#branch-{{ $branch->id }}" class="btn btn-sm btn-outline-success rounded-pill text-nowrap">Atur <i class="ri-arrow-right-line"></i></a>
                        </div>
                    @endif
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success"><i class="ri-save-line me-1"></i>Simpan Notifikasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Isi titik lokasi dari GPS HP yang membuka halaman ini.
        (() => {
            const form = document.querySelector('[data-place-location]');
            if (!form) return;
            const message = form.querySelector('[data-location-message]');
            const say = (text, tone) => { message.innerHTML = ''; const el = document.createElement('span'); el.className = 'text-' + tone; el.textContent = text; message.appendChild(el); };

            form.querySelector('[data-use-location]').addEventListener('click', () => {
                if (!navigator.geolocation) { say('Browser ini tidak bisa membaca lokasi.', 'danger'); return; }
                say('Membaca lokasi...', 'muted');
                navigator.geolocation.getCurrentPosition((pos) => {
                    form.latitude.value = pos.coords.latitude.toFixed(7);
                    form.longitude.value = pos.coords.longitude.toFixed(7);
                    say('Lokasi terbaca (akurasi sekitar ' + Math.round(pos.coords.accuracy) + ' m). Tekan Simpan.', pos.coords.accuracy > 100 ? 'warning' : 'success');
                }, () => say('Lokasi belum bisa dibaca. Izinkan akses lokasi di browser, lalu coba lagi.', 'danger'), { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            });
        })();
    </script>
@endpush
