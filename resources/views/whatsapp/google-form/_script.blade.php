{{-- Tab "Pasang Script": Apps Script siap salin + URL webhook. $integration --}}
@php
    $webhookUrl = $integration->webhookUrl();
    $script = <<<JS
// bizbos: kirim jawaban Google Form ke WhatsApp
function onFormSubmit(e) {
  var data = {};
  e.response.getItemResponses().forEach(function (item) {
    data[item.getItem().getTitle()] = item.getResponse();
  });
  var response = UrlFetchApp.fetch("{$webhookUrl}", {
    method: "post",
    contentType: "application/json",
    payload: JSON.stringify(data),
    muteHttpExceptions: true
  });
  Logger.log(response.getResponseCode() + " " + response.getContentText());
}
JS;
@endphp
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-0">
            <div class="card-body px-4">
                <ol class="small ps-3 mb-3">
                    <li class="mb-1">Buka Google Form Anda, klik menu <b>⋮ › Apps Script</b> (atau <b>Extensions › Apps Script</b>).</li>
                    <li class="mb-1">Hapus isi editor, tempel script di bawah, lalu klik <b>Simpan</b>.</li>
                    <li class="mb-1">Klik ikon jam <b>Triggers › Add Trigger</b>, pilih fungsi <code>onFormSubmit</code>, event <b>On form submit</b>, simpan dan izinkan aksesnya.</li>
                    <li>Coba isi form sekali. Hasilnya langsung muncul di tab <b>Pendaftar</b>.</li>
                </ol>
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
