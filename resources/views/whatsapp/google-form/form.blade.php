@extends('layouts.dashboard')

@section('title', $integration->exists ? 'Ubah Google Form' : 'Hubungkan Google Form')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3">
        <a href="{{ $integration->exists ? route('whatsapp.google-forms.show', $integration) : route('whatsapp.google-forms.index', ['branch' => $branchId]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali</a>
    </div>

    <form method="POST" action="{{ $integration->exists ? route('whatsapp.google-forms.update', $integration) : route('whatsapp.google-forms.store') }}" class="row g-3">
        @csrf
        @if ($integration->exists) @method('PUT') @endif
        <input type="hidden" name="branch_id" value="{{ $branchId }}">

        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">{{ $integration->exists ? 'Ubah '.$integration->name : 'Hubungkan Google Form' }}</h6>
                    <div class="small text-muted">Setiap kali form diisi, pengisinya langsung menerima pesan WhatsApp dari nomor Anda.</div>
                </div>
                <div class="card-body px-4">
                    <label class="form-label">Nama integrasi</label>
                    <input type="text" name="name" maxlength="150" required class="form-control mb-3 @error('name') is-invalid @enderror" value="{{ old('name', $integration->name) }}" placeholder="mis. Pendaftaran Seminar Oktober">

                    <label class="form-label">Kirim dari nomor</label>
                    <select name="device_id" required class="form-select mb-3">
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected(old('device_id', $integration->device_id) === $device->id)>{{ $device->name }} {{ $device->phone ? '(+'.$device->phone.')' : '' }}</option>
                        @endforeach
                    </select>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Judul pertanyaan nomor WhatsApp</label>
                            <input type="text" name="phone_field" maxlength="191" required class="form-control @error('phone_field') is-invalid @enderror" value="{{ old('phone_field', $integration->phone_field) }}" placeholder="mis. Nomor WhatsApp">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Judul pertanyaan nama <span class="text-muted small">(opsional)</span></label>
                            <input type="text" name="name_field" maxlength="191" class="form-control" value="{{ old('name_field', $integration->name_field) }}" placeholder="mis. Nama Lengkap">
                        </div>
                    </div>
                    <div class="form-text mb-3">Tulis sama persis dengan judul pertanyaan di Google Form. Nama dipakai di laporan dan tiket QR.</div>

                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <label class="form-label mb-0">Isi pesan</label>
                        @if ($templates->isNotEmpty())
                            <select class="form-select form-select-sm w-auto" data-template-picker>
                                <option value="">Ambil dari template...</option>
                                @foreach ($templates as $i => $template)
                                    <option value="{{ $i }}">{{ $template['name'] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <textarea name="message" rows="6" maxlength="4000" required class="form-control mb-1 @error('message') is-invalid @enderror" placeholder="Halo @{{Nama Lengkap}}, terima kasih sudah mendaftar!">{{ old('message', $integration->message) }}</textarea>
                    <div class="form-text mb-3">Pakai <code>@{{Judul Pertanyaan}}</code>, <code>@{{link_bayar}}</code>, <code>@{{link_qr}}</code>, <code>@{{kode}}</code>. Link yang tidak ditulis ditambahkan otomatis.</div>

                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $integration->is_active))>
                        <label class="form-check-label" for="is_active">Aktif, kirim WhatsApp setiap form masuk</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Pembayaran --}}
            <div class="card mb-3">
                <div class="card-body px-4">
                    <div class="form-check form-switch mb-1">
                        <input type="hidden" name="payment_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="payment_enabled" value="1" id="payment_enabled" data-toggle-section="#payment-section"
                            @checked(old('payment_enabled', $integration->payment_enabled)) @disabled(! $invoicesActive && ! $integration->payment_enabled)>
                        <label class="form-check-label fw-semibold" for="payment_enabled">Aktifkan pembayaran</label>
                    </div>
                    <div class="small text-muted">Pendaftar menerima link bayar. Uangnya masuk saldo Anda seperti invoice.</div>
                    @error('payment_enabled') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @unless ($invoicesActive)
                        <div class="small text-warning mt-1"><i class="ri-information-line"></i> Butuh paket Tagihan (Invoice) yang aktif di branch ini.</div>
                    @endunless
                    <div id="payment-section" class="mt-3">
                        <label class="form-label">Nominal per pendaftar</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="amount" data-rupiah class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $integration->amount) }}" placeholder="150.000">
                        </div>
                        @error('amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="form-text">Minimal Rp {{ number_format($setting->min_amount, 0, ',', '.') }}.</div>
                    </div>
                </div>
            </div>

            {{-- Registrasi ulang --}}
            <div class="card mb-3">
                <div class="card-body px-4">
                    <div class="form-check form-switch mb-1">
                        <input type="hidden" name="registration_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="registration_enabled" value="1" id="registration_enabled" data-toggle-section="#registration-section"
                            @checked(old('registration_enabled', $integration->registration_enabled))>
                        <label class="form-check-label fw-semibold" for="registration_enabled">Aktifkan registrasi ulang</label>
                    </div>
                    <div class="small text-muted">Pendaftar menerima link QR seperti tiket. Petugas memindainya di lokasi lewat aplikasi registrasi ulang.</div>
                    <div id="registration-section" class="mt-3">
                        <label class="form-label">PIN petugas (6 angka)</label>
                        <input type="password" name="checkin_pin" inputmode="numeric" maxlength="6" autocomplete="new-password" class="form-control @error('checkin_pin') is-invalid @enderror"
                            placeholder="{{ $integration->checkin_pin_hash ? 'Kosongkan bila tidak diganti' : 'mis. 482916' }}">
                        @error('checkin_pin') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <div class="form-text">Berikan PIN ini hanya ke petugas di lokasi. Mengganti PIN membuat petugas yang sedang login harus memasukkan PIN baru.</div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary w-100">{{ $integration->exists ? 'Simpan' : 'Lanjut, tampilkan script' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            // Bagian pengaturan hanya tampil saat slide-nya aktif.
            document.querySelectorAll('[data-toggle-section]').forEach(function (toggle) {
                var section = document.querySelector(toggle.dataset.toggleSection);
                var sync = function () { section.hidden = !toggle.checked; };
                toggle.addEventListener('change', sync);
                sync();
            });

            var picker = document.querySelector('[data-template-picker]');
            if (!picker) return;
            var templates = @json($templates->pluck('text'));
            picker.addEventListener('change', function () {
                var text = templates[picker.value], area = document.querySelector('[name="message"]');
                if (text === undefined) return;
                if (area.value.trim() && !confirm('Ganti isi pesan dengan template ini?')) { picker.value = ''; return; }
                area.value = text;
                picker.value = '';
            });
        })();
    </script>
@endpush
