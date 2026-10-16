@extends('layouts.dashboard')

@section('title', 'Message')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <ul class="nav nav-pills">
                    @foreach (['' => 'Semua'] + \App\Models\WaBroadcast::TYPES as $key => $label)
                        <li class="nav-item"><a class="nav-link py-1 {{ request('type', '') === $key ? 'active' : '' }}" href="{{ route('whatsapp.messages.index', array_filter(['branch' => $branch->id, 'type' => $key])) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <a href="{{ route('whatsapp.messages.create', ['branch' => $branch->id, 'type' => 'once']) }}" class="btn btn-outline-primary btn-sm"><i class="ri-send-plane-line"></i> Sekali Kirim</a>
                    <a href="{{ route('whatsapp.messages.create', ['branch' => $branch->id, 'type' => 'blast']) }}" class="btn btn-primary btn-sm"><i class="ri-broadcast-line"></i> Blast</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 820px">
                    <thead class="table-light">
                        <tr><th>Pesan</th><th>Jenis</th><th>Device</th><th>Jadwal</th><th>Kiriman Terakhir</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($broadcasts as $item)
                            @php([$label, $color] = $item->statusMeta())
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    <div class="small text-muted text-truncate" style="max-width: 280px">{{ $item->body ?: '📎 '.$item->upload_name }}</div>
                                </td>
                                <td>{{ \App\Models\WaBroadcast::TYPES[$item->type] }}</td>
                                <td class="small">{{ $item->device?->name }}</td>
                                <td class="small text-nowrap">
                                    {{ \App\Models\WaBroadcast::SCHEDULES[$item->schedule] }}
                                    @if ($item->next_run_at && $item->status === 'active')
                                        <div class="text-muted">berikutnya {{ $item->next_run_at->translatedFormat('d M Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($run = $item->latestCampaign)
                                        {{ $run->sent }} / {{ $run->total }} terkirim
                                        @if ($run->failed) <span class="text-danger">· {{ $run->failed }} gagal</span> @endif
                                    @else
                                        <span class="text-muted">{{ $item->last_error ?? 'Belum dikirim' }}</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $color }}-subtle text-{{ $color }}">{{ $label }}</span></td>
                                <td class="text-end"><a href="{{ route('whatsapp.messages.show', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pesan. Kirim ke beberapa kontak dengan <strong>Sekali Kirim</strong>, atau ke Group kontak dengan <strong>Blast</strong>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $broadcasts->links() }}</div>
        </div>
    </div>
@endsection
