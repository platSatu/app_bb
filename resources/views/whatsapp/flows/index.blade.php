@extends('layouts.dashboard')

@section('title', 'Chatbot Flow')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">Menu bertingkat yang dimulai saat pelanggan mengetik kata kunci (mis. <em>menu</em>). Pelanggan memilih dengan membalas angka.</p>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <a href="{{ route('whatsapp.flows.create', ['branch' => $branch->id]) }}" class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Buat Flow</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Nama</th><th>Kata kunci</th><th>Device</th><th class="text-center">Langkah</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        {{-- Flow Jadwal bawaan: tampil seperti flow lain, tidak bisa diubah / dihapus di sini. --}}
                        @if ($scheduleBot)
                            <tr class="table-success" style="--bs-table-bg: var(--bs-success-bg-subtle)">
                                <td class="fw-semibold"><i class="ri-robot-2-line text-success me-1"></i>Flow Jadwal <span class="badge bg-light text-dark border ms-1">Bawaan</span></td>
                                <td class="small">Otomatis dari pengingat les & absen · <em>menu</em> (khusus admin)</td>
                                <td class="small">Semua device</td>
                                <td class="text-center">3 alur</td>
                                <td><span class="badge {{ $scheduleBot['on'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $scheduleBot['on'] ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('whatsapp.flows.schedule', ['branch' => $branch->id]) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-git-branch-line"></i> Lihat alur</a>
                                    @if ($scheduleBot['url'])
                                        <a href="{{ $scheduleBot['url'] }}" class="btn btn-sm rounded-pill btn-outline-success" title="Atur di Schedules › Notifikasi"><i class="ri-settings-3-line"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        @forelse ($flows as $flow)
                            <tr>
                                <td class="fw-semibold">{{ $flow->name }}</td>
                                <td class="small">{{ implode(', ', $flow->keywords ?? []) }}</td>
                                <td class="small">{{ $flow->device?->name ?? 'Semua device' }}</td>
                                <td class="text-center">{{ count($flow->nodes ?? []) }}</td>
                                <td><span class="badge {{ $flow->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $flow->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('whatsapp.flows.edit', $flow) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-pencil-line"></i> Ubah</a>
                                    <form method="POST" action="{{ route('whatsapp.flows.destroy', $flow) }}" class="d-inline" onsubmit="return confirm('Hapus flow ini? Pelanggan yang sedang di dalam flow akan berhenti.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            @unless ($scheduleBot)
                                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada chatbot flow.</td></tr>
                            @endunless
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
