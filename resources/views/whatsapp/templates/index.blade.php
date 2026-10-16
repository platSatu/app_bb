@extends('layouts.dashboard')

@section('title', 'Template')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">Pesan siap pakai lengkap dengan header, footer, link, dan lampiran.</p>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <a href="{{ route('whatsapp.templates.create', ['branch' => $branch->id]) }}" class="btn btn-primary btn-sm"><i class="ri-add-line"></i> Buat Template</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Nama</th><th>Isi</th><th>Tambahan</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td class="fw-semibold">{{ $template->name }}</td>
                                <td class="small text-muted text-truncate" style="max-width: 320px">{{ $template->header ? $template->header.' · ' : '' }}{{ $template->body }}</td>
                                <td class="small text-nowrap">
                                    @if ($template->link) <span class="badge bg-info-subtle text-info"><i class="ri-link"></i> Link</span> @endif
                                    @if ($template->file_path) <span class="badge bg-primary-subtle text-primary"><i class="ri-attachment-2"></i> {{ ucfirst($template->fileKind()) }}</span> @endif
                                    @if (! $template->link && ! $template->file_path) <span class="text-muted">-</span> @endif
                                </td>
                                <td><span class="badge {{ $template->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('whatsapp.templates.edit', $template) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-pencil-line"></i> Ubah</a>
                                    <form method="POST" action="{{ route('whatsapp.templates.destroy', $template) }}" class="d-inline" onsubmit="return confirm('Hapus template ini beserta lampirannya?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada template. Buat yang pertama yuk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
