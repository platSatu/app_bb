@extends('layouts.dashboard')

@section('title', 'Integrasi Web')
@section('page-section', 'WhatsApp')

@php($tab = request('tab') === 'docs' ? 'docs' : 'keys')

@push('styles')
    @include('whatsapp.third-party._docs-style')
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="text-muted small mb-0">Website atau aplikasi Anda bisa mengirim WhatsApp lewat nomor di bizbos, mis. notifikasi pesanan atau OTP.</p>
        @include('whatsapp._branch-filter')
    </div>

    @include('whatsapp._tabs', ['active' => $tab, 'tabs' => [
        'keys' => ['API Key per Nomor', 'ri-key-2-line', route('whatsapp.third-party.index', ['branch' => $branch->id])],
        'docs' => ['Dokumentasi API', 'ri-book-open-line', route('whatsapp.third-party.index', ['branch' => $branch->id, 'tab' => 'docs'])],
    ]])

    @if ($tab === 'keys')
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Nomor WhatsApp</th><th>API Key</th><th>Dipakai terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($devices as $device)
                                @php($key = $device->apiKey)
                                <tr>
                                    <td><div class="fw-semibold">{{ $device->name }}</div><div class="small text-muted">{{ $device->phone ? '+'.$device->phone : 'Belum terhubung' }}</div></td>
                                    <td class="font-monospace small">{{ $key ? \Illuminate\Support\Str::limit($key->api_key, 18) : '-' }}</td>
                                    <td class="small">{{ $key?->last_used_at?->diffForHumans() ?? ($key ? 'Belum pernah' : '-') }}</td>
                                    <td>
                                        @if (! $key) <span class="badge bg-light text-muted border">Belum dibuat</span>
                                        @else <span class="badge {{ $key->is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">{{ $key->is_active ? 'Aktif' : 'Nonaktif' }}</span> @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('whatsapp.api-key.show', $device) }}" class="btn btn-sm rounded-pill {{ $key ? 'btn-outline-secondary' : 'btn-outline-primary' }}">
                                            <i class="{{ $key ? 'ri-settings-3-line' : 'ri-add-line' }}"></i> {{ $key ? 'Kelola' : 'Buat API Key' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada nomor WhatsApp di branch ini. <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Hubungkan nomor dulu</a>.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2">
            <i class="ri-share-line text-primary"></i>
            <span class="small flex-grow-1">Link publik untuk developer Anda (tanpa login, tanpa data akun):</span>
            <div class="input-group input-group-sm" style="max-width: 420px;">
                <input type="text" class="form-control font-monospace" value="{{ route('docs.whatsapp-api') }}" readonly>
                <button class="btn btn-outline-secondary" type="button" data-copy="{{ route('docs.whatsapp-api') }}"><i class="ri-file-copy-line"></i></button>
            </div>
        </div>
        <div class="card"><div class="card-body api-docs">{!! $docs !!}</div></div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
