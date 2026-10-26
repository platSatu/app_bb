@extends('layouts.dashboard')

@section('title', 'API Key WhatsApp')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3">
        <a href="{{ route('whatsapp.third-party.index', ['branch' => $device->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke Integrasi Web</a>
    </div>

    @if ($secret = session('secret'))
        <div class="alert alert-warning">
            <h6 class="alert-heading"><i class="ri-key-2-line me-1"></i>Simpan sekarang, hanya ditampilkan sekali</h6>
            <div class="input-group mt-2">
                <input type="text" class="form-control font-monospace" value="{{ $secret }}" readonly>
                <button class="btn btn-outline-dark" type="button" data-copy="{{ $secret }}"><i class="ri-file-copy-line"></i> Salin</button>
            </div>
            <div class="small mt-2">Simpan di server aplikasi Anda (mis. file .env), jangan di kode frontend/JavaScript.</div>
        </div>
    @endif

    @unless ($key)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ri-plug-line fs-1 text-primary"></i>
                <h5 class="mt-2">Kirim WhatsApp dari aplikasi Anda</h5>
                <p class="text-muted mx-auto" style="max-width: 560px">Website atau sistem Anda bisa mengirim pesan lewat nomor <strong>{{ $device->name }}</strong> ({{ $device->phone ? '+'.$device->phone : 'belum terhubung' }}), misalnya notifikasi pesanan atau pengingat.</p>
                <form method="POST" action="{{ route('whatsapp.api-key.store', $device) }}">
                    @csrf
                    <button class="btn btn-primary"><i class="ri-key-2-line me-1"></i>Buat API Key</button>
                </form>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Kredensial &middot; {{ $device->name }}</h6>
                            <span class="badge {{ $key->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $key->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <label class="form-label small mb-1">API Host</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace" value="{{ url('/') }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy="{{ url('/') }}"><i class="ri-file-copy-line"></i></button>
                        </div>
                        <label class="form-label small mb-1">Token (X-WA-Token)</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace" value="{{ $key->api_key }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy="{{ $key->api_key }}"><i class="ri-file-copy-line"></i></button>
                        </div>
                        <label class="form-label small mb-1">Secret Key (X-WA-Secret)</label>
                        <input type="text" class="form-control mb-3" value="Tersimpan aman, hanya ditampilkan sekali saat dibuat" disabled>
                        <div class="small text-muted mb-3">Dipakai terakhir: {{ $key->last_used_at?->diffForHumans() ?? 'belum pernah' }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('whatsapp.api-key.regenerate', $device) }}" onsubmit="return confirm('Secret Key lama langsung tidak berlaku. Lanjutkan?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning"><i class="ri-refresh-line"></i> Buat Secret Key Baru</button>
                            </form>
                            <form method="POST" action="{{ route('whatsapp.api-key.toggle', $device) }}">
                                @csrf
                                <button class="btn btn-sm {{ $key->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $key->is_active ? 'Nonaktifkan API' : 'Aktifkan API' }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-0 h-100">
                    <div class="card-body small">
                        <h6>Cara pakai</h6>
                        <p class="mb-2">Di aplikasi Anda cukup isi <b>API Host</b>, <b>Token</b>, dan <b>Secret Key</b> di atas. Format sama dengan API teleios/konexa, jadi aplikasi yang sudah terhubung tidak perlu diubah kodenya.</p>
                        <ul class="mb-2 ps-3">
                            <li><code>POST /api/wa-api/v1/send-message</code> &rarr; JSON <code>{"to": "6281...", "message": "Halo"}</code></li>
                            <li><code>POST /api/wa-api/v1/send-media</code> &rarr; multipart <code>to, file, caption</code> (maks 64 MB)</li>
                            <li><code>GET /api/wa-api/v1/messages/{id}</code> &rarr; status: queued, sent, failed, skipped</li>
                            <li><code>GET /api/wa-api/v1/usage</code> &rarr; paket, sisa kuota pesan, jumlah request</li>
                        </ul>
                        <p class="text-muted mb-2">Header wajib: <code>X-WA-Token</code> dan <code>X-WA-Secret</code>, selalu lewat <b>https</b>. Maksimal 20 request per menit; pesan dikirim berjeda supaya nomor aman dari pembatasan WhatsApp.</p>
                        <a href="{{ route('whatsapp.third-party.index', ['branch' => $device->branch_id, 'tab' => 'docs']) }}" class="btn btn-sm btn-outline-primary"><i class="ri-book-open-line"></i> Dokumentasi lengkap &amp; contoh kode</a>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body">
                        <h6 class="mb-2">Request Terakhir</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Waktu</th><th>Request</th><th>Status</th><th>IP</th></tr></thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                        <tr>
                                            <td class="text-nowrap small">{{ $log->created_at->translatedFormat('d M Y H:i:s') }}</td>
                                            <td class="font-monospace small">{{ $log->method }} /{{ $log->path }}</td>
                                            <td><span class="badge {{ $log->status < 300 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $log->status }}</span></td>
                                            <td class="small text-muted">{{ $log->ip }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada request.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endunless
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
