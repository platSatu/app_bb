@extends('layouts.dashboard')

@section('title', 'AI Bot')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">AI menjawab pesan yang tidak ditangani Chatbot Flow & Auto Reply, memakai API key AI milik Anda sendiri. Satu bot per nomor WhatsApp.</p>
                @include('whatsapp._branch-filter')
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Device</th><th>AI Bot</th><th>Penyedia</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php($bot = $bots->get($device->id))
                            <tr>
                                <td><div class="fw-semibold">{{ $device->name }}</div><div class="small text-muted">{{ $device->phone ? '+'.$device->phone : '-' }}</div></td>
                                <td>{{ $bot?->name ?? '-' }}</td>
                                <td class="small">{{ $bot ? \App\Models\WaAiBot::DRIVERS[$bot->driver][0].' · '.$bot->model : '-' }}</td>
                                <td>
                                    @if ($bot)
                                        <span class="badge {{ $bot->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $bot->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    @else
                                        <span class="badge bg-light text-muted">Belum diatur</span>
                                    @endif
                                </td>
                                <td class="text-end"><a href="{{ route('whatsapp.ai-bots.edit', $device) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-robot-2-line"></i> {{ $bot ? 'Atur' : 'Buat AI Bot' }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada nomor WhatsApp di branch ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
