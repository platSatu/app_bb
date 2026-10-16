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
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada chatbot flow.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
