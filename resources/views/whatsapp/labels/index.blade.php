@extends('layouts.dashboard')

@section('title', 'Label')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <p class="text-muted small mb-0">Label untuk menandai chat di inbox, mis. <em>Prospek</em>, <em>Sudah bayar</em>, <em>Komplain</em>.</p>
                        @include('whatsapp._branch-filter')
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Label</th><th class="text-center">Dipakai di chat</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($labels as $label)
                                    <tr>
                                        <td><span class="badge rounded-pill px-3 py-2" style="background: {{ $label->color }}; color: #fff">{{ $label->name }}</span></td>
                                        <td class="text-center">{{ $label->chats_count }}</td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" data-edit="{{ route('whatsapp.labels.update', $label) }}" data-values='@json($label->only('name', 'color'))'><i class="ri-pencil-line"></i> Ubah</button>
                                            <form method="POST" action="{{ route('whatsapp.labels.destroy', $label) }}" class="d-inline" onsubmit="return confirm('Hapus label ini? Label juga dilepas dari semua chat.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada label.</td></tr>
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
                    <h6 class="mb-3" data-form-title>Buat Label</h6>
                    <form method="POST" action="{{ route('whatsapp.labels.store') }}" data-form data-store="{{ route('whatsapp.labels.store') }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST">
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" maxlength="50" required class="form-control mb-1 @error('name') is-invalid @enderror" value="{{ old('name') }}">
                        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <label class="form-label mt-2">Warna</label>
                        <input type="color" name="color" class="form-control form-control-color mb-3" value="{{ old('color', '#16a34a') }}">
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
    @include('whatsapp._edit-form-script', ['createTitle' => 'Buat Label', 'editTitle' => 'Ubah Label'])
@endpush
