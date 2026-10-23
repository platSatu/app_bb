@extends('layouts.dashboard')

@section('title', 'Group')
@section('page-section', 'Phone Book')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <p class="text-muted small mb-0">Kelompok kontak branch <strong>{{ $branch->name }}</strong>, dipakai untuk blast dan pesan terjadwal.</p>
                        @include('whatsapp._branch-filter')
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nama</th><th>Keterangan</th><th class="text-center">Anggota</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($groups as $group)
                                    <tr>
                                        <td class="fw-semibold">{{ $group->name }}</td>
                                        <td class="small text-muted">{{ $group->description ?: '-' }}</td>
                                        <td class="text-center">{{ number_format($group->customers_count) }}</td>
                                        <td class="text-end text-nowrap">
                                            <a href="{{ route('whatsapp.groups.show', $group) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-team-line"></i> Anggota</a>
                                            <form method="POST" action="{{ route('whatsapp.groups.destroy', $group) }}" class="d-inline" onsubmit="return confirm('Hapus group ini? Kontaknya tetap ada di Phone Book.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada group. Buat group pertama di samping.</td></tr>
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
                    <h6 class="mb-3">Buat Group</h6>
                    <form method="POST" action="{{ route('whatsapp.groups.store') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <label class="form-label">Nama group</label>
                        <input type="text" name="name" maxlength="100" required class="form-control mb-1 @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Contoh: Pelanggan VIP">
                        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <label class="form-label mt-2">Keterangan (opsional)</label>
                        <input type="text" name="description" maxlength="255" class="form-control mb-3" value="{{ old('description') }}">
                        <button class="btn btn-primary w-100">Buat Group</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">Anggota grup WhatsApp juga bisa disalin ke sini dari menu <a href="{{ route('whatsapp.wa-groups.index', ['branch' => $branch->id]) }}">Group WhatsApp</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
