{{-- Panduan Google Form (tab terakhir halaman Google Form). --}}
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-0 h-100">
            <div class="card-body px-4">
                <h5 class="fw-semibold">Cara kerjanya</h5>
                <p class="text-muted">Orang mengisi Google Form Anda, lalu bizbos langsung mengirim WhatsApp ke nomor yang mereka tulis di form. Tidak perlu developer.</p>

                <h6 class="fw-semibold mt-4">Pasang dalam 3 langkah</h6>
                <ol class="ps-3">
                    <li class="mb-2">Klik <b>Hubungkan Google Form</b>, pilih nomor pengirim, tulis judul pertanyaan yang berisi nomor WhatsApp, lalu tulis pesannya.</li>
                    <li class="mb-2">Di halaman berikutnya, salin <b>script Apps Script</b>. Buka Google Form Anda, menu <b>⋮ › Apps Script</b>, tempel, lalu simpan.</li>
                    <li>Di Apps Script, klik ikon jam <b>Triggers › Add Trigger</b>, pilih <code>onFormSubmit</code> dan event <b>On form submit</b>. Selesai, coba isi form sekali.</li>
                </ol>

                <h6 class="fw-semibold mt-4">Fitur tambahan (opsional)</h6>
                <ul class="ps-3">
                    <li class="mb-2"><b>Pembayaran</b>: pendaftar menerima link bayar (VA, QRIS, e-wallet). Uangnya masuk ke saldo Anda seperti invoice biasa. Butuh paket Tagihan aktif.</li>
                    <li class="mb-2"><b>Registrasi ulang</b>: pendaftar menerima link QR. Kalau pembayaran juga aktif, QR baru dikirim setelah lunas. Di lokasi acara, petugas membuka <b>aplikasi registrasi ulang</b> (link + PIN), lalu scan QR atau ketik kodenya.</li>
                    <li><b>Laporan</b>: siapa yang daftar, pesan terkirim atau gagal, sudah bayar, dan sudah hadir. Bisa diunduh ke Excel dan dibuka di Google Sheets (<b>File › Import</b>).</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-0 h-100">
            <div class="card-body px-4">
                <h6 class="fw-semibold">Kata yang bisa dipakai di pesan</h6>
                <table class="table table-sm small mb-3">
                    <tbody>
                        <tr><td><code>@{{Judul Pertanyaan}}</code></td><td>Jawaban pengisi, mis. <code>@{{Nama Lengkap}}</code></td></tr>
                        <tr><td><code>@{{link_bayar}}</code></td><td>Link pembayaran (kalau pembayaran aktif)</td></tr>
                        <tr><td><code>@{{link_qr}}</code></td><td>Link QR registrasi ulang</td></tr>
                        <tr><td><code>@{{kode}}</code></td><td>Kode registrasi ulang (10 huruf/angka)</td></tr>
                    </tbody>
                </table>
                <p class="small text-muted">Link bayar dan link QR yang tidak Anda tulis di pesan akan ditambahkan otomatis di bagian bawah, jadi tidak ada yang terlewat.</p>
                <h6 class="fw-semibold mt-3">Contoh pesan</h6>
                <pre class="bg-light p-2 rounded small mb-0" style="white-space: pre-wrap;">Halo @{{Nama Lengkap}} 👋
Terima kasih sudah mendaftar *Seminar Bisnis*.
Silakan selesaikan pembayaran di sini:
@{{link_bayar}}</pre>
            </div>
        </div>
    </div>
</div>
