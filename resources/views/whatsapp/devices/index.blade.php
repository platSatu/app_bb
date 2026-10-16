@extends('layouts.dashboard')

@section('title', 'Device & Inbox')
@section('page-section', 'WhatsApp')

@push('styles')
    <style>
        .wa-status-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
        .wa-dot-connected { background: #22c55e; animation: wa-pulse 1.5s infinite; --c: 34, 197, 94; }
        .wa-dot-disconnected { background: #ef4444; --c: 239, 68, 68; }
        .wa-dot-pending { background: #f59e0b; animation: wa-pulse 1.5s infinite; --c: 245, 158, 11; }
        @keyframes wa-pulse { 0% { box-shadow: 0 0 0 0 rgba(var(--c), .6); } 70% { box-shadow: 0 0 0 8px rgba(var(--c), 0); } 100% { box-shadow: 0 0 0 0 rgba(var(--c), 0); } }
        .wa-actions { display: flex; flex-wrap: nowrap; justify-content: flex-end; gap: 6px; }
        .wa-actions .btn { width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: none; }
        .wa-pair-code { font-family: monospace; font-size: 2rem; letter-spacing: .3rem; font-weight: 700; }
    </style>
@endpush

@section('content')
    <div class="card" id="wa-devices"
        data-list-url="{{ route('whatsapp.devices.list', ['branch' => $branch->id]) }}"
        data-connect-url="{{ route('whatsapp.devices.connect', '__ID__') }}"
        data-status-url="{{ route('whatsapp.devices.status', '__ID__') }}"
        data-logout-url="{{ route('whatsapp.devices.logout', '__ID__') }}"
        data-health-url="{{ route('whatsapp.devices.health', '__ID__') }}"
        data-auto-connect="{{ request('connect') }}"
        data-open-add="{{ $errors->has('name') ? 1 : '' }}"
        data-statuses='@json(\App\Models\WaDevice::STATUS_LABELS)'>
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Nomor WhatsApp</h5>
                    <p class="text-muted small mb-0">Hubungkan nomor WhatsApp branch <strong>{{ $branch->name }}</strong>, lalu balas chat pelanggan dari Inbox.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#wa-add-modal"><i class="ri-add-line"></i> Tambah Device</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 820px">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>No Handphone</th>
                            <th>Status</th>
                            <th class="text-nowrap">Terakhir Terhubung</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php($meta = $device->statusMeta())
                            <tr data-device="{{ $device->id }}" data-linked="{{ $device->isLinked() ? 1 : 0 }}" data-name="{{ $device->name }}" data-cap="{{ $device->daily_cap }}">
                                <td class="fw-semibold">{{ $device->name }}</td>
                                <td class="text-nowrap" data-phone>{{ $device->phone ? '+'.$device->phone : '-' }}</td>
                                <td>
                                    <span class="badge d-inline-flex align-items-center bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }}" data-status-badge title="{{ $device->status_reason }}">
                                        <span class="wa-status-dot {{ $meta['dot'] }}"></span><span data-status-label>{{ $meta['label'] }}</span>
                                    </span>
                                    <div class="small text-muted mt-1 text-truncate" style="max-width: 260px" data-status-reason>{{ $device->isConnected() ? '' : $device->status_reason }}</div>
                                </td>
                                <td class="small text-muted text-nowrap">{{ $device->last_connected_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                <td>
                                    <div class="wa-actions">
                                        <a href="{{ route('whatsapp.inbox.show', $device) }}" class="btn bg-primary-subtle text-primary {{ $device->isLinked() ? '' : 'disabled' }}" title="Inbox" data-inbox><i class="ri-message-3-line"></i></a>
                                        <button type="button" class="btn bg-secondary-subtle text-secondary-emphasis" title="{{ $device->isLinked() ? 'Sambung ulang' : 'Hubungkan' }}" data-action="connect"><i class="ri-refresh-line"></i></button>
                                        <a href="{{ route('whatsapp.api-key.show', $device) }}" class="btn bg-dark-subtle text-dark-emphasis" title="API Key"><i class="ri-key-2-line"></i></a>
                                        <button type="button" class="btn bg-success-subtle text-success" title="Kesehatan" data-action="health"><i class="ri-heart-pulse-line"></i></button>
                                        <button type="button" class="btn bg-info-subtle text-info-emphasis" title="Ubah" data-action="edit"><i class="ri-pencil-line"></i></button>
                                        <button type="button" class="btn bg-danger-subtle text-danger" title="Disconnect" data-action="logout" @disabled(! $device->isLinked())><i class="ri-link-unlink-m"></i></button>
                                        <form method="POST" action="{{ route('whatsapp.devices.destroy', $device) }}" onsubmit="return confirm('Hapus perangkat ini beserta semua riwayat chatnya? Tindakan ini tidak bisa dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn bg-danger-subtle text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada device. Klik "Tambah Device" untuk menghubungkan nomor WhatsApp pertama.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('whatsapp.devices._modals')
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="{{ asset('js/wa-common.js') }}"></script>
    <script src="{{ asset('js/wa-devices.js') }}"></script>
@endpush
