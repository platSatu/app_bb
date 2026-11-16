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

                    {{-- Kirim hasil: memakai device pengirim yang sama dengan OTP di atas. --}}
                    <hr class="my-4">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="send_result" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="send_result" name="send_result" value="1" @checked(old('send_result', $page->setting('quiz.send_result', false)))>
                        <label class="form-check-label" for="send_result">Kirim hasil ke WhatsApp peserta</label>
                        <div class="form-text">Dikirim sekali, segera setelah peserta menyelesaikan quiz. Butuh langganan WhatsApp aktif dan device pengirim di atas sudah dipilih.</div>
                    </div>
                    <div class="mb-4">
                        <label for="result_text" class="form-label">Teks pesan hasil</label>
                        <textarea id="result_text" name="result_text" rows="5" maxlength="1000"
                            class="form-control @error('result_text') is-invalid @enderror">{{ old('result_text', $page->setting('quiz.result_text')) }}</textarea>
                        <div class="form-text">
                            Kosongkan untuk memakai teks bawaan. Kata yang diganti otomatis:
                            <code>{nama}</code> <code>{quiz}</code> <code>{skor}</code> <code>{maks}</code> <code>{persen}</code> <code>{referensi}</code>
                        </div>
                        @error('result_text') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>

                    {{-- Grade dihitung dari PERSENTASE skor, bukan jumlah soal benar: kalau soalnya
                         ditambah, batas "50% ke atas" tetap benar sedangkan "5 soal benar" jadi salah. --}}
                    <hr class="my-4">
                    <label class="form-label mb-1">Grade Hasil</label>
                    <div class="form-text mb-3">Isi dari nilai tertinggi ke terendah. Peserta mendapat grade pertama yang persentasenya tercapai. Kosongkan semua kalau tidak perlu grade.</div>
                    @php($grades = old('grades', $page->setting('quiz.grades') ?: []))
                    @for ($i = 0; $i < \App\Services\Quiz\QuizService::MAX_GRADES; $i++)
                        <div class="row g-2 mb-2">
                            <div class="col-5 col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text">&ge;</span>
                                    <input type="number" name="grades[{{ $i }}][min]" min="0" max="100" class="form-control" value="{{ data_get($grades, $i.'.min') }}" aria-label="Batas persen grade {{ $i + 1 }}">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-7 col-md-3">
                                <input type="text" name="grades[{{ $i }}][label]" maxlength="20" class="form-control" placeholder="Grade, mis. A" value="{{ data_get($grades, $i.'.label') }}">
                            </div>
                            <div class="col-12 col-md-6">
                                <input type="text" name="grades[{{ $i }}][note]" maxlength="120" class="form-control" placeholder="Keterangan, mis. Cocok masuk kelas Intermediate" value="{{ data_get($grades, $i.'.note') }}">
                            </div>
                        </div>
                    @endfor
                    <div class="form-text mb-4">Baris tanpa nama grade diabaikan. Di pesan WhatsApp, pakai <code>{grade}</code> untuk satu baris jadi, atau <code>{grade_nama}</code> dan <code>{grade_info}</code> kalau mau disusun sendiri.</div>

                    {{-- Pembayaran selalu DI DEPAN: lunas dulu, baru soal terbuka. Tidak ada mode bayar
                         di belakang, supaya tidak pernah ada jawaban menggantung yang belum dibayar. --}}
                    <hr class="my-4">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="payment_on" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="payment_on" name="payment_on" value="1" @checked(old('payment_on', $page->setting('quiz.payment_on', false)))>
                        <label class="form-check-label" for="payment_on">Quiz berbayar</label>
                        <div class="form-text">Peserta membayar dulu, soal terbuka setelah pembayarannya dikonfirmasi. Tiap kali mengulang, bayar lagi.</div>
                    </div>
                    <div class="mb-2">
                        <label for="amount" class="form-label">Biaya mengikuti quiz</label>
                        <div class="input-group" style="max-width: 280px;">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="amount" name="amount" min="1000" max="50000000" step="1000"
                                class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $page->setting('quiz.amount')) }}" placeholder="50000">
                        </div>
                        @error('amount') <div class="text-danger fs-13">{{ $message }}</div> @enderror
                    </div>
                    <div class="alert alert-warning py-2 fs-13 mb-4">
                        <b>Yang masuk ke Saldo Anda adalah biaya ini dikurangi potongan payment gateway</b>, sama seperti penjualan katalog.
                        Besar potongannya mengikuti metode bayar yang dipilih peserta. Quiz tidak dikenai biaya layanan bizbos.
                        Kalau Anda ingin menerima angka bulat, naikkan biayanya.
                        <br>Nominal terkecil yang diterima ditentukan payment gateway dan berbeda per metode bayar, jadi angka yang terlalu kecil bisa ditolak saat peserta memilih metodenya.
                    </div>

                    @if ($canManage)
                        <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Simpan</button></div>
                    @endif
                </fieldset>
            </form>
        </div>
    </div>
@endsection
