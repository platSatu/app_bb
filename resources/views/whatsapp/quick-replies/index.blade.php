@extends('layouts.dashboard')

@section('title', 'Quick Reply')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <p class="text-muted small mb-0">Jawaban siap pakai. Di inbox, ketik <code>/</code> di kotak pesan untuk memilihnya.</p>
                        @include('whatsapp._branch-filter')
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Judul</th><th>Shortcut</th><th>Isi</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($replies as $reply)
                                    <tr>
                                        <td class="fw-semibold">{{ $reply->title }}</td>
                                        <td>{!! $reply->shortcut ? '<code>/'.e($reply->shortcut).'</code>' : '-' !!}</td>
                                        <td class="small text-muted text-truncate" style="max-width: 320px">{{ $reply->body }}</td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" data-edit="{{ route('whatsapp.quick-replies.update', $reply) }}" data-values='@json($reply->only('title', 'shortcut', 'body'))'><i class="ri-pencil-line"></i> Ubah</button>
                                            <form method="POST" action="{{ route('whatsapp.quick-replies.destroy', $reply) }}" class="d-inline" onsubmit="return confirm('Hapus quick reply ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada quick reply.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-0">
                <div class="card-body">
                    <h6 class="mb-3" data-form-title>Buat Quick Reply</h6>
                    <form method="POST" action="{{ route('whatsapp.quick-replies.store') }}" data-form data-store="{{ route('whatsapp.quick-replies.store') }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST">
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <label class="form-label">Judul</label>
                        <input type="text" name="title" maxlength="100" required class="form-control mb-2 @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="Contoh: Info rekening">
                        <label class="form-label">Shortcut (opsional)</label>
                        <div class="input-group mb-1">
                            <span class="input-group-text">/</span>
                            <input type="text" name="shortcut" maxlength="30" class="form-control @error('shortcut') is-invalid @enderror" value="{{ old('shortcut') }}" placeholder="rekening">
                        </div>
                        @error('shortcut') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <label class="form-label mt-2">Isi pesan</label>
                        <textarea name="body" rows="6" maxlength="4000" required class="form-control mb-3 @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1">Simpan</button>
                            <button type="button" class="btn btn-light d-none" data-cancel>Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('whatsapp._edit-form-script', ['createTitle' => 'Buat Quick Reply', 'editTitle' => 'Ubah Quick Reply'])
@endpush
