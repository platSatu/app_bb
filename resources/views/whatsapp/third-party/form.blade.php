@extends('layouts.dashboard')

@section('title', $integration->exists ? 'Ubah Google Form' : 'Hubungkan Google Form')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3">
        <a href="{{ $integration->exists ? route('whatsapp.third-party.forms.show', $integration) : route('whatsapp.third-party.index', ['branch' => $branchId]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ $integration->exists ? route('whatsapp.third-party.forms.update', $integration) : route('whatsapp.third-party.forms.store') }}" class="card mb-0">
                @csrf
                @if ($integration->exists) @method('PUT') @endif
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">{{ $integration->exists ? 'Ubah '.$integration->name : 'Hubungkan Google Form' }}</h6>
                    <div class="small text-muted">Setiap kali form diisi, pengisinya langsung menerima pesan WhatsApp dari nomor Anda.</div>
                </div>
                <div class="card-body px-4">
                    <label class="form-label">Nama integrasi</label>
                    <input type="text" name="name" maxlength="150" required class="form-control mb-3 @error('name') is-invalid @enderror" value="{{ old('name', $integration->name) }}" placeholder="mis. Pendaftaran Seminar Oktober">
                    @error('name') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror

                    <label class="form-label">Kirim dari nomor</label>
                    <select name="device_id" required class="form-select mb-3 @error('device_id') is-invalid @enderror">
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected(old('device_id', $integration->device_id) === $device->id)>{{ $device->name }} {{ $device->phone ? '(+'.$device->phone.')' : '' }}</option>
                        @endforeach
                    </select>

                    <label class="form-label">Judul pertanyaan nomor WhatsApp di Google Form</label>
                    <input type="text" name="phone_field" maxlength="191" required class="form-control mb-1 @error('phone_field') is-invalid @enderror" value="{{ old('phone_field', $integration->phone_field) }}" placeholder="mis. Nomor WhatsApp">
                    @error('phone_field') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text mb-3">Tulis sama persis dengan judul pertanyaannya. Huruf besar/kecil tidak masalah.</div>

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
                    <textarea name="message" rows="7" maxlength="4000" required class="form-control mb-1 @error('message') is-invalid @enderror" placeholder="Halo @{{Nama Lengkap}}, terima kasih sudah mendaftar! Kami tunggu kehadiran Anda ya.">{{ old('message', $integration->message) }}</textarea>
                    @error('message') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text mb-3">Pakai <code>@{{Judul Pertanyaan}}</code> untuk menyisipkan jawaban pengisi. Format WhatsApp berlaku: *tebal*, _miring_.</div>

                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $integration->is_active))>
                        <label class="form-check-label" for="is_active">Aktif, kirim WhatsApp setiap form masuk</label>
                    </div>
                </div>
                <div class="card-footer bg-white px-4 py-3 d-flex justify-content-end gap-2">
                    <button class="btn btn-primary">{{ $integration->exists ? 'Simpan' : 'Lanjut, tampilkan script' }}</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card mb-0">
                <div class="card-body small">
                    <h6>Contoh</h6>
                    <p class="text-muted mb-2">Google Form Anda punya pertanyaan <b>Nama Lengkap</b>, <b>Nomor WhatsApp</b>, dan <b>Kelas</b>. Isi pesannya:</p>
                    <pre class="bg-light p-2 rounded mb-2" style="white-space: pre-wrap;">Halo @{{Nama Lengkap}} 👋
Pendaftaran kelas *@{{Kelas}}* sudah kami terima.
Sampai jumpa di kelas pertama ya!</pre>
                    <p class="text-muted mb-0">Yang diterima Budi: "Halo Budi Santoso 👋 Pendaftaran kelas <b>Piano</b> sudah kami terima..."</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var picker = document.querySelector('[data-template-picker]');
            if (!picker) return;
            var templates = @json($templates->pluck('text'));
            picker.addEventListener('change', function () {
                var text = templates[picker.value];
                var area = document.querySelector('[name="message"]');
                if (text === undefined) return;
                if (area.value.trim() && !confirm('Ganti isi pesan dengan template ini?')) { picker.value = ''; return; }
                area.value = text;
                picker.value = '';
            });
        })();
    </script>
@endpush
