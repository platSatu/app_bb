{{-- Tab "Pasang Script": Apps Script siap salin + URL webhook. $integration --}}
@php
    $webhookUrl = $integration->webhookUrl();
    // Pasang sekali dengan fungsi "pasang" (trigger dibuat otomatis, tidak perlu menu Triggers).
    $script = <<<JS
// bizbos: kirim jawaban Google Form ke WhatsApp.
// Cara pakai: Simpan, pilih fungsi "pasang" di atas, klik Jalankan, lalu izinkan.
var URL_BIZBOS = "{$webhookUrl}";

// 1x saja: menyambungkan form ini ke bizbos.
function pasang() {
  var form = FormApp.getActiveForm();
  if (!form) {
    throw new Error("Script ini harus dibuka dari Google Form (titik tiga > Apps Script), bukan dari Google Sheets.");
  }
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === "kirimKeBizbos") ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger("kirimKeBizbos").forForm(form).onFormSubmit().create();
  Logger.log("BERHASIL. Form sudah tersambung ke bizbos. Sekarang coba isi form-nya sekali.");
}

// Kirim ulang jawaban terakhir, untuk tes tanpa mengisi form lagi.
function tesKirim() {
  var jawaban = FormApp.getActiveForm().getResponses();
  if (!jawaban.length) throw new Error("Belum ada jawaban. Isi Google Form-nya sekali dulu.");
  kirimKeBizbos({ response: jawaban[jawaban.length - 1] });
}

// Dipanggil otomatis setiap ada yang mengisi form. Jangan dijalankan manual.
function kirimKeBizbos(e) {
  var data = {};
  if (e && e.response) {
    e.response.getItemResponses().forEach(function (item) {
      data[item.getItem().getTitle()] = item.getResponse();
    });
    var email = e.response.getRespondentEmail();
    if (email) data["Email"] = email;
  } else if (e && e.namedValues) {
    Object.keys(e.namedValues).forEach(function (judul) {
      data[judul] = e.namedValues[judul].join(", ");
    });
  } else {
    throw new Error("Fungsi ini jalan otomatis saat form diisi. Untuk mencoba, jalankan fungsi tesKirim.");
  }
  var res = UrlFetchApp.fetch(URL_BIZBOS, {
    method: "post",
    contentType: "application/json",
    payload: JSON.stringify(data),
    muteHttpExceptions: true
  });
  Logger.log("bizbos menjawab (" + res.getResponseCode() + "): " + res.getContentText());
}
JS;
@endphp
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-0">
            <div class="card-body px-4">
                <h6 class="fw-semibold mb-2">Pasang di Google Form (±3 menit, cukup sekali)</h6>
                <ol class="small ps-3 mb-3">
                    <li>Buka <b>Google Form</b> Anda dalam mode edit, klik <b>⋮</b> (titik tiga, kanan atas) › <b>Apps Script</b>.
                        <div class="text-muted">Harus dari Google Form, <b>bukan</b> dari Google Sheets jawaban.</div></li>
                    <li>Di editor, hapus semua tulisan yang ada (<code>function myFunction() {}</code>), lalu klik <b>Salin Script</b> di bawah dan tempel. Klik ikon <b>💾 Simpan</b>.</li>
                    <li>Di toolbar atas editor, pilih fungsi <b><code>pasang</code></b> pada dropdown, lalu klik <b>▶ Jalankan</b> (Run).</li>
                    <li>Muncul <b>Otorisasi diperlukan</b>: klik <b>Tinjau izin</b> › pilih akun Google Anda.
                        Kalau muncul <i>"Google belum memverifikasi aplikasi ini"</i>, klik <b>Lanjutan</b> › <b>Buka … (tidak aman)</b> › <b>Izinkan</b>.
                        <div class="text-muted">Aman: script ini milik Anda sendiri, hanya membaca jawaban form dan mengirimnya ke bizbos.</div></li>
                    <li>Di <b>Log eksekusi</b> bawah muncul <b>BERHASIL</b>. Sekarang isi Google Form sekali, lalu buka tab <b>Pendaftar</b> di sini. Data dan status WhatsApp-nya muncul dalam beberapa detik.</li>
                </ol>
                <details class="small mb-3">
                    <summary class="fw-semibold text-primary">Tidak muncul di Pendaftar? Cek di sini</summary>
                    <ul class="ps-3 mt-2 mb-0">
                        <li class="mb-1">Di Apps Script, pilih fungsi <code>tesKirim</code>, klik <b>▶ Jalankan</b>. Jawaban terakhir dikirim ulang dan balasan bizbos tampil di log.</li>
                        <li class="mb-1"><b>"harus dibuka dari Google Form"</b>: Anda membuka Apps Script dari Google Sheets. Ulangi dari langkah 1 di Google Form.</li>
                        <li class="mb-1"><b>"jalan otomatis saat form diisi"</b>: Anda menjalankan <code>kirimKeBizbos</code>. Yang dijalankan cukup <code>pasang</code> (sekali) atau <code>tesKirim</code>.</li>
                        <li class="mb-1"><b>404 Webhook tidak ditemukan</b>: URL webhook sudah diganti. Salin ulang script di bawah, tempel, simpan.</li>
                        <li class="mb-1">Muncul tapi <b>gagal: kolom tidak ditemukan</b>: samakan <i>Kolom nomor WhatsApp</i> di Edit dengan judul pertanyaan di form, persis.</li>
                        <li>Riwayat lengkap ada di Apps Script › menu kiri <b>Eksekusi</b> (ikon ≡▶).</li>
                    </ul>
                </details>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small mb-0 fw-semibold">Script Apps Script</label>
                    <button type="button" class="btn btn-sm btn-primary" data-copy="{{ $script }}"><i class="ri-file-copy-line"></i> Salin Script</button>
                </div>
                <pre class="bg-dark text-light p-3 rounded small mb-0" style="max-height: 340px; overflow: auto;"><code>{{ $script }}</code></pre>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-0">
            <div class="card-body px-4">
                <h6 class="fw-semibold">URL Webhook</h6>
                <p class="small text-muted">Sudah ada di dalam script. URL ini adalah kuncinya. Kalau pernah tersebar, ganti saja lalu salin ulang script-nya.</p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control font-monospace" value="{{ $webhookUrl }}" readonly>
                    <button class="btn btn-outline-secondary" type="button" data-copy="{{ $webhookUrl }}"><i class="ri-file-copy-line"></i></button>
                </div>
                <form method="POST" action="{{ route('whatsapp.google-forms.regenerate', $integration) }}" onsubmit="return confirm('URL lama langsung tidak berlaku dan script di Google Form harus diganti. Lanjutkan?')">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning"><i class="ri-refresh-line"></i> Ganti URL Webhook</button>
                </form>
                <hr>
                <h6 class="fw-semibold">Pesan yang dikirim</h6>
                <div class="bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ $integration->message }}</div>
            </div>
        </div>
    </div>
</div>
