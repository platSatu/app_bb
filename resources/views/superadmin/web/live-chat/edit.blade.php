@extends('layouts.dashboard')

@section('title', 'Live Chat')
@section('page-section', 'Settings')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <form class="card" method="POST" action="{{ route('superadmin.web.live-chat.update') }}">
                @csrf
                @method('PUT')
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Live Chat bizbos.id</h6>
                    <div class="text-muted small lh-sm">Tombol chat di kanan bawah bizbos.id. Pengunjung mengisi nama, email, dan WhatsApp dulu (masuk ke <b>Data User › Customer</b> sebagai "Website (Live Chat)"), lalu pertanyaannya dijawab dari <b>Website › FAQ</b>.</div>
                </div>
                <div class="card-body p-4">
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="chat_enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="chat_enabled" name="chat_enabled" value="1" @checked(old('chat_enabled', $setting->chat_enabled ?? true))>
                        <label class="form-check-label fw-semibold" for="chat_enabled">Tampilkan live chat di bizbos.id</label>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="chat_ai" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="chat_ai" name="chat_ai" value="1" @checked(old('chat_ai', $setting->chat_ai)) @disabled(! $provider)>
                        <label class="form-check-label fw-semibold" for="chat_ai">Jawab memakai AI</label>
                        <div class="form-text">Mati = jawaban diambil dari FAQ yang paling cocok, disapa dengan nama pengunjung (tanpa biaya AI). Cukup tambah atau ubah FAQ untuk merawat jawabannya.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="chat_greeting">Sapaan pembuka</label>
                        <input type="text" class="form-control @error('chat_greeting') is-invalid @enderror" id="chat_greeting" name="chat_greeting" maxlength="300"
                            value="{{ old('chat_greeting', $setting->chat_greeting) }}" placeholder="Halo! 👋 Saya asisten bizbos. Mau tanya soal fitur, harga paket, atau cara mulai?">
                        @error('chat_greeting')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Kosongkan untuk memakai sapaan bawaan (contoh di kolom).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="chat_questions">Pertanyaan cepat</label>
                        <textarea class="form-control @error('chat_questions') is-invalid @enderror" id="chat_questions" name="chat_questions" rows="4"
                            placeholder="Apa itu bizbos?&#10;Berapa harga paketnya?&#10;Bisa coba gratis?&#10;Bagaimana cara daftar?">{{ old('chat_questions', $setting->chat_questions) }}</textarea>
                        @error('chat_questions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Satu pertanyaan per baris, maks 6. Muncul sebagai tombol yang tinggal diklik pengunjung.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="chat_knowledge">Catatan untuk asisten <span class="text-muted fw-normal">(opsional)</span></label>
                        <textarea class="form-control @error('chat_knowledge') is-invalid @enderror" id="chat_knowledge" name="chat_knowledge" rows="7" maxlength="6000"
                            placeholder="Contoh:&#10;- Jam layanan tim: Senin–Jumat 09.00–17.00 WIB.&#10;- Promo bulan ini: diskon 20% paket tahunan dengan kode HEMAT20.&#10;- Jangan menjanjikan fitur custom; arahkan ke WhatsApp.">{{ old('chat_knowledge', $setting->chat_knowledge) }}</textarea>
                        @error('chat_knowledge')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Info yang belum ada di menu Website, atau arahan cara menjawab. Tidak ditampilkan ke pengunjung.</div>
                    </div>
                </div>
                <div class="card-footer bg-white px-4 py-3 text-end">
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>Simpan</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body p-4 d-flex gap-3">
                    @if ($provider)
                        <i class="ri-robot-2-line fs-3 text-success"></i>
                        <div class="small">
                            <div class="fw-semibold">AI tersedia: {{ $provider->name }} <span class="text-muted">({{ $provider->model }})</span></div>
                            <div class="text-muted">{{ $setting->chat_ai ? 'Live chat dijawab AI berdasarkan konten website. Kuota AI terpakai setiap pesan (maks 8 pesan/menit per pengunjung).' : 'Live chat saat ini menjawab dari FAQ tanpa AI. Nyalakan "Jawab memakai AI" kalau ingin jawaban dari AI.' }}</div>
                        </div>
                    @else
                        <i class="ri-error-warning-line fs-3 text-warning"></i>
                        <div class="small">
                            <div class="fw-semibold">Belum ada AI aktif</div>
                            <div class="text-muted">Live chat menjawab dari FAQ yang paling cocok, disapa dengan nama pengunjung. Tambah FAQ di menu <b>Website › FAQ</b> supaya makin banyak pertanyaan yang terjawab.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-1 fw-semibold">Coba tanya</h6>
                    <div class="text-muted small lh-sm">Uji jawaban asisten setelah menyimpan perubahan.</div>
                </div>
                <div class="card-body p-4">
                    <form id="chatTest" class="d-flex gap-2">
                        <input type="text" class="form-control" name="message" maxlength="500" placeholder="Misal: berapa harga paket?" required>
                        <button type="submit" class="btn btn-outline-primary flex-shrink-0"><i class="ri-send-plane-2-line"></i></button>
                    </form>
                    <div id="chatTestReply" class="mt-3 p-3 rounded-3 bg-light small" style="white-space: pre-wrap;" hidden></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('chatTest').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this, out = document.getElementById('chatTestReply'), btn = form.querySelector('button');
            btn.disabled = true;
            out.hidden = false;
            out.textContent = 'Sedang berpikir...';
            fetch(@json(route('superadmin.web.live-chat.test')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                body: JSON.stringify({ message: form.message.value })
            }).then(function (r) { return r.json(); })
              .then(function (d) { out.textContent = d.reply || d.message || 'Tidak ada jawaban.'; })
              .catch(function () { out.textContent = 'Gagal menghubungi server. Coba lagi.'; })
              .finally(function () { btn.disabled = false; });
        });
    </script>
@endpush
