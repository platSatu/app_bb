{{-- Panduan Google Form (tab terakhir halaman Google Form). --}}
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-0 h-100">
            <div class="card-body px-4">
                <h5 class="fw-semibold">Cara kerjanya</h5>
                <p class="text-muted">Orang mengisi Google Form Anda, lalu bizbos langsung mengirim WhatsApp ke nomor yang mereka tulis di form. Tidak perlu developer.</p>

                <h6 class="fw-semibold mt-4">Langkah pemasangan</h6>
                <ol class="ps-3">
                    <li class="mb-2"><b>Siapkan Google Form.</b> Wajib ada satu pertanyaan untuk nomor WhatsApp (mis. judul <i>Nomor WhatsApp</i>, jenis Jawaban singkat). Sebaiknya ada juga pertanyaan <i>Nama Lengkap</i>.</li>
                    <li class="mb-2"><b>Hubungkan di bizbos.</b> Klik <b>Hubungkan Google Form</b>, pilih nomor pengirim, lalu tulis judul pertanyaan nomor WhatsApp <b>persis sama</b> dengan di form. Tulis pesannya, simpan.</li>
                    <li class="mb-2"><b>Pasang script.</b> Di halaman detail, buka tab <b>Pasang Script</b> dan ikuti 5 langkahnya: buka Google Form › <b>⋮ › Apps Script</b>, tempel script, simpan, jalankan fungsi <code>pasang</code>, lalu izinkan aksesnya.</li>
                    <li><b>Coba.</b> Isi form sekali dengan nomor WhatsApp Anda sendiri. Pesan masuk ke WhatsApp dan datanya muncul di tab <b>Pendaftar</b>.</li>
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
