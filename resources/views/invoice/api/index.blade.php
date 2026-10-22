@extends('layouts.dashboard')

@section('title', 'API & Integrasi')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice._tabs')

    @if ($branches->count() > 1)
        <form method="GET" class="mb-3 d-flex gap-2 align-items-center">
            <label class="small text-muted">Branch</label>
            <select name="branch" class="form-select w-auto" onchange="this.form.submit()">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>
    @endif

    @if ($secrets = session('secrets'))
        <div class="alert alert-warning">
            <h6 class="alert-heading"><i class="ri-key-2-line me-1"></i>Simpan sekarang, hanya ditampilkan sekali</h6>
            @foreach (['secret' => 'Secret Key (untuk tanda tangan API)', 'webhook_secret' => 'Webhook Secret (untuk cek webhook dari bizbos)'] as $key => $label)
                @if (isset($secrets[$key]))
                    <label class="form-label small mb-1 mt-2">{{ $label }}</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" value="{{ $secrets[$key] }}" readonly>
                        <button class="btn btn-outline-dark" type="button" data-copy="{{ $secrets[$key] }}"><i class="ri-file-copy-line"></i> Salin</button>
                    </div>
                @endif
            @endforeach
            <div class="small mt-2">Simpan di file .env website Anda, jangan di kode frontend/JavaScript.</div>
        </div>
    @endif

    @unless ($client)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ri-plug-line fs-1 text-primary"></i>
                <h5 class="mt-2">Hubungkan website Anda dengan bizbos</h5>
                <p class="text-muted mx-auto" style="max-width: 560px">Buat invoice langsung dari website atau aplikasi Anda, customer membayar lewat popup bizbos, dan Anda menerima kabar lunas lewat webhook. Uangnya masuk ke saldo bizbos branch <strong>{{ $branch->name }}</strong>.</p>
                <form method="POST" action="{{ route('invoices.api.store') }}">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <button class="btn btn-primary"><i class="ri-key-2-line me-1"></i>Buat API Key</button>
                </form>
                <a href="{{ route('invoices.docs') }}" class="d-inline-block mt-3">Baca dokumentasi integrasi</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Kredensial</h6>
                            <span class="badge {{ $client->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $client->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <label class="form-label small mb-1">Base URL API</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace" value="{{ url('/api/v1') }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy="{{ url('/api/v1') }}"><i class="ri-file-copy-line"></i></button>
                        </div>
                        <label class="form-label small mb-1">API Key (X-Bizbos-Key)</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace" value="{{ $client->api_key }}" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy="{{ $client->api_key }}"><i class="ri-file-copy-line"></i></button>
                        </div>
                        <label class="form-label small mb-1">Script popup</label>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control font-monospace small" value="<script src=&quot;{{ asset('js/bizbos-pay.js') }}&quot;></script>" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-copy='<script src="{{ asset('js/bizbos-pay.js') }}"></script>'><i class="ri-file-copy-line"></i></button>
                        </div>
                        <div class="small text-muted mb-3">Dipakai terakhir: {{ $client->last_used_at?->diffForHumans() ?? 'belum pernah' }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach (['secret' => 'Buat Secret Key Baru', 'webhook_secret' => 'Buat Webhook Secret Baru'] as $which => $label)
                                <form method="POST" action="{{ route('invoices.api.regenerate', $client) }}" onsubmit="return confirm('Yang lama langsung tidak berlaku. Lanjutkan?')">
                                    @csrf
                                    <input type="hidden" name="which" value="{{ $which }}">
                                    <button class="btn btn-sm btn-outline-warning"><i class="ri-refresh-line"></i> {{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <h6 class="mb-3">Webhook & Popup</h6>
                        <form method="POST" action="{{ route('invoices.api.update', $client) }}">
                            @csrf
                            @method('PUT')
                            <label for="webhook_url" class="form-label small mb-1">URL Webhook</label>
                            <input type="url" id="webhook_url" name="webhook_url" maxlength="500" class="form-control mb-1 @error('webhook_url') is-invalid @enderror" value="{{ old('webhook_url', $client->webhook_url) }}" placeholder="https://tokosaya.com/bizbos/webhook">
                            @error('webhook_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text mb-3">bizbos mengirim invoice.paid, invoice.expired, invoice.cancelled, dan payout.* (Disbursement) ke URL ini.</div>

                            <label for="allowed_origins" class="form-label small mb-1">Website yang boleh membuka popup bayar</label>
                            <textarea id="allowed_origins" name="allowed_origins" rows="3" class="form-control font-monospace small @error('allowed_origins.*') is-invalid @enderror" placeholder="https://tokosaya.com&#10;http://localhost">{{ implode("\n", (array) old('allowed_origins', $client->allowed_origins ?? [])) }}</textarea>
                            @error('allowed_origins.*') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text mb-3">Satu per baris, tanpa path. Hanya website ini yang menerima kabar dari popup.</div>

                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked($client->is_active)>
                                <label class="form-check-label" for="is_active">API aktif</label>
                            </div>
                            <button class="btn btn-primary btn-sm">Simpan</button>
                        </form>
                        <form method="POST" action="{{ route('invoices.api.test', $client) }}" class="mt-2">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" @disabled(! $client->webhook_url)><i class="ri-send-plane-line"></i> Kirim Webhook Test</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Log Webhook Terakhir</h6>
                            <a href="{{ route('invoices.docs') }}" class="small"><i class="ri-book-open-line"></i> Dokumentasi integrasi</a>
                        </div>
                        @include('invoice.api._logs', ['logs' => $logs])
                    </div>
                </div>
            </div>
        </div>
    @endunless

    @include('invoice.invoices._table-script')
@endsection
