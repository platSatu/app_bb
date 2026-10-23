@extends('layouts.dashboard')

@section('title', $integration->name)
@section('page-section', 'WhatsApp')

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
    $device = $integration->device;
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <a href="{{ route('whatsapp.third-party.index', ['branch' => $integration->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke Third Party</a>
        <div class="d-flex gap-2">
            <a href="{{ route('whatsapp.third-party.forms.edit', $integration) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-pencil-line"></i> Ubah</a>
            <form method="POST" action="{{ route('whatsapp.third-party.forms.destroy', $integration) }}" onsubmit="return confirm('Hapus integrasi ini? Form yang masuk setelahnya tidak dibalas WhatsApp lagi.')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.75rem; height: 2.75rem;"><i class="ri-file-list-3-line fs-4"></i></div>
            <div class="flex-grow-1 min-w-0">
                <h5 class="mb-0">{{ $integration->name }}</h5>
                <div class="small text-muted">
                    Dikirim dari {{ $device?->name ?? '-' }} {{ $device?->phone ? '(+'.$device->phone.')' : '' }}
                    · nomor diambil dari pertanyaan "<b>{{ $integration->phone_field }}</b>"
                </div>
            </div>
            <span class="badge {{ $integration->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} fs-6">{{ $integration->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        @if ($device && ! $device->isConnected())
            <div class="alert alert-warning mx-3 mb-3"><i class="ri-error-warning-line me-1"></i>Nomor pengirim sedang tidak terhubung ke WhatsApp. Form tetap tercatat, tapi pesannya gagal terkirim sampai nomornya dihubungkan lagi di menu Device &amp; Inbox.</div>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card mb-3">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Pasang di Google Form (sekali saja)</h6></div>
                <div class="card-body px-4">
                    <ol class="small ps-3 mb-3">
                        <li class="mb-1">Buka Google Form Anda, klik menu <b>⋮ › Apps Script</b> (atau <b>Extensions › Apps Script</b>).</li>
                        <li class="mb-1">Hapus isi editor, lalu tempel script di bawah dan klik <b>Simpan</b>.</li>
                        <li class="mb-1">Klik ikon jam <b>Triggers › Add Trigger</b>, pilih fungsi <code>onFormSubmit</code>, event <b>On form submit</b>, lalu simpan dan izinkan aksesnya.</li>
                        <li>Coba isi form sekali. Hasilnya langsung muncul di <b>Riwayat Form Masuk</b>.</li>
                    </ol>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small mb-0 fw-semibold">Script Apps Script</label>
                        <button type="button" class="btn btn-sm btn-primary" data-copy="{{ $script }}"><i class="ri-file-copy-line"></i> Salin Script</button>
                    </div>
                    <pre class="bg-dark text-light p-3 rounded small mb-0" style="max-height: 320px; overflow: auto;"><code>{{ $script }}</code></pre>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-body px-4">
                    <h6 class="fw-semibold">URL Webhook</h6>
                    <p class="small text-muted">Sudah ada di dalam script. URL ini adalah kuncinya, siapa pun yang memegangnya bisa memicu pesan. Kalau pernah tersebar, ganti saja.</p>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control font-monospace" value="{{ $webhookUrl }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy="{{ $webhookUrl }}"><i class="ri-file-copy-line"></i></button>
                    </div>
                    <form method="POST" action="{{ route('whatsapp.third-party.forms.regenerate', $integration) }}" onsubmit="return confirm('URL lama langsung tidak berlaku dan script di Google Form harus diganti. Lanjutkan?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-warning"><i class="ri-refresh-line"></i> Ganti URL Webhook</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card mb-3">
                <div class="card-body px-4">
                    <h6 class="fw-semibold">Pesan yang dikirim</h6>
                    <div class="bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ $integration->message }}</div>
                </div>
            </div>

            <div class="card mb-0">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Riwayat Form Masuk</h6>
                    <span class="small text-muted">{{ number_format($integration->trigger_count) }} terkirim</span>
                </div>
                <div class="card-body px-4">
                    @forelse ($submissions as $submission)
                        <div class="py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="small">
                                    <span class="fw-semibold">{{ $submission->phone ? '+'.$submission->phone : 'Nomor tidak ditemukan' }}</span>
                                    <span class="text-muted">· {{ $submission->created_at->translatedFormat('d M Y H:i') }}</span>
                                </div>
                                <span class="badge {{ $submission->status === 'sent' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $submission->status === 'sent' ? 'Terkirim' : 'Gagal' }}</span>
                            </div>
                            @if ($submission->error) <div class="small text-danger mt-1">{{ $submission->error }}</div> @endif
                            <details class="small mt-1">
                                <summary class="text-muted" style="cursor: pointer;">Lihat jawaban</summary>
                                <dl class="row mb-0 mt-1">
                                    @foreach ($submission->payload as $question => $answer)
                                        <dt class="col-5 fw-normal text-muted text-truncate">{{ $question }}</dt><dd class="col-7 mb-1 text-break">{{ $answer !== '' ? $answer : '-' }}</dd>
                                    @endforeach
                                </dl>
                            </details>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-inbox-line fs-2 d-block mb-1"></i>
                            Belum ada form yang masuk. Setelah script terpasang, coba isi form Anda sekali.
                        </div>
                    @endforelse
                    <div class="mt-2">{{ $submissions->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
