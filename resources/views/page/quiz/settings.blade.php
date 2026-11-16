@extends('layouts.dashboard')

@section('title', 'Pengaturan Quiz')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'quiz-settings'])

    <div class="card" style="max-width: 640px;">
        <div class="card-header bg-white border-bottom px-4 py-3">
            <h6 class="mb-1 fw-semibold">Pengaturan Quiz</h6>
            <div class="text-muted small lh-sm">Peserta hanya melihat skor setelah selesai.</div>
        </div>
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="{{ route($routes.'.quiz.settings.update', $page) }}">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $canManage)>
                    <div class="mb-3">
                        <label for="time_limit" class="form-label">Batas Waktu</label>
                        <div class="input-group" style="max-width: 240px;">
                            <input type="number" id="time_limit" name="time_limit" min="1" max="600" class="form-control @error('time_limit') is-invalid @enderror"
                                value="{{ old('time_limit', $page->setting('quiz.time_limit')) }}" placeholder="Tanpa batas">
                            <span class="input-group-text">menit</span>
                        </div>
                        <div class="form-text">Kosongkan kalau tanpa batas waktu. Waktu dihitung di server sejak peserta mulai.</div>
                        @error('time_limit') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="allow_retry" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="allow_retry" name="allow_retry" value="1" @checked(old('allow_retry', $page->setting('quiz.allow_retry', false)))>
                        <label class="form-check-label" for="allow_retry">Boleh mengerjakan berkali-kali</label>
                        <div class="form-text">Kalau dimatikan, satu nomor WhatsApp hanya bisa mengerjakan 1 kali.</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="navigation" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="navigation" name="navigation" value="1" @checked(old('navigation', $page->setting('quiz.navigation', true)))>
                        <label class="form-check-label" for="navigation">Navigasi nomor soal</label>
                        <div class="form-text">Nyala: satu soal per layar dengan nomor soal di samping. Mati: semua soal tampil berurutan dalam satu halaman.</div>
                    </div>
                    {{-- Gerbang kode. PIN tetap ikut disimpan walau mode OTP, sebagai jalan masuk
                         cadangan kalau WhatsApp sedang bermasalah -- supaya quiz tidak mati total. --}}
                    @php($gate = old('gate', $page->setting('quiz.gate')))
                    <hr class="my-4">
                    <div class="mb-3">
                        <label for="gate" class="form-label">Kunci Akses</label>
                        <select id="gate" name="gate" class="form-select @error('gate') is-invalid @enderror" style="max-width: 320px;">
                            <option value="" @selected(! $gate)>Tanpa kunci, siapa pun bisa mengerjakan</option>
                            <option value="pin" @selected($gate === 'pin')>PIN tetap yang Anda bagikan sendiri</option>
                            <option value="otp" @selected($gate === 'otp')>Kode dikirim ke WhatsApp peserta</option>
                        </select>
                        @error('gate') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="pin" class="form-label">PIN {!! $gate === 'otp' ? '<span class="text-muted fw-normal">(cadangan, opsional)</span>' : '' !!}</label>
                        <input type="text" id="pin" name="pin" maxlength="12" class="form-control @error('pin') is-invalid @enderror"
                            style="max-width: 240px;" value="{{ old('pin', $page->setting('quiz.pin')) }}" placeholder="Misal: KELAS2026">
                        <div class="form-text">4 sampai 12 karakter. Pada mode WhatsApp, PIN ini tetap bisa dipakai masuk kalau pengiriman kode sedang bermasalah.</div>
                        @error('pin') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="device_id" class="form-label">Pengirim Kode WhatsApp</label>
                        @if (! $waActive)
                            <div class="alert alert-warning py-2 fs-13 mb-2">Langganan WhatsApp branch ini belum aktif, jadi kode tidak bisa dikirim. Pakai mode PIN dulu, atau aktifkan paket WhatsApp-nya.</div>
                        @elseif ($devices->isEmpty())
                            <div class="alert alert-warning py-2 fs-13 mb-2">Belum ada device WhatsApp di branch ini. Hubungkan device dulu di menu WhatsApp &rsaquo; Device &amp; Inbox.</div>
                        @endif
                        <div class="d-flex flex-wrap gap-2">
                            <select id="device_id" name="device_id" class="form-select @error('device_id') is-invalid @enderror" style="max-width: 320px;">
                                <option value="">-- Pilih device --</option>
                                @foreach ($devices as $device)
                                    <option value="{{ $device->id }}" @selected(old('device_id', $page->setting('quiz.device_id')) === $device->id)>{{ $device->name }}</option>
                                @endforeach
                            </select>
                            <div class="input-group" style="max-width: 220px;">
                                <input type="number" name="code_minutes" min="1" max="60" class="form-control @error('code_minutes') is-invalid @enderror"
                                    value="{{ old('code_minutes', $page->setting('quiz.code_minutes', 10)) }}" aria-label="Masa berlaku kode">
                                <span class="input-group-text">menit berlaku</span>
                            </div>
                        </div>
                        <div class="form-text">Kode 6 angka, sekali pakai. Satu nomor boleh minta maksimal 3 kali per 10 menit, dan satu quiz maksimal 30 kali per jam, supaya kuota WhatsApp Anda tidak bisa dihabiskan orang iseng.</div>
                        @error('device_id') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                        @error('code_minutes') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>

                    @if ($canManage)
                        <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Simpan</button></div>
                    @endif
                </fieldset>
            </form>
        </div>
    </div>
@endsection
