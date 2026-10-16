@extends('layouts.dashboard')

@section('title', 'Group · '.$group->name)
@section('page-section', 'Phone Book')

@section('content')
    <div class="mb-3">
        <a href="{{ route('whatsapp.groups.index', ['branch' => $group->branch_id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar group</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0">Anggota ({{ number_format($members->total()) }})</h6>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control form-control-sm" maxlength="100" placeholder="Cari anggota" value="{{ request('search') }}">
                            <button class="btn btn-sm btn-outline-secondary"><i class="ri-search-line"></i></button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nama</th><th>WhatsApp</th><th>Email</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($members as $member)
                                    <tr>
                                        <td class="fw-semibold">{{ $member->name ?: '-' }}</td>
                                        <td class="text-nowrap">{{ $member->phone ? '+'.$member->phone : '-' }}</td>
                                        <td>{{ $member->email ?: '-' }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('whatsapp.groups.members.destroy', [$group, $member]) }}" onsubmit="return confirm('Keluarkan kontak ini dari group?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-user-unfollow-line"></i> Keluarkan</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada anggota. Pilih kontak di samping.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $members->links() }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Ubah Group</h6>
                    <form method="POST" action="{{ route('whatsapp.groups.update', $group) }}">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" maxlength="100" required class="form-control mb-2 @error('name') is-invalid @enderror" value="{{ old('name', $group->name) }}">
                        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <input type="text" name="description" maxlength="255" class="form-control mb-2" value="{{ old('description', $group->description) }}" placeholder="Keterangan">
                        <button class="btn btn-sm btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
            <div class="card mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Tambah Anggota dari Buku Kontak</h6>
                    @include('invoice._customer-picker', ['available' => $available, 'action' => route('whatsapp.groups.members.store', $group), 'button' => 'Tambahkan'])
                    <a href="{{ route('whatsapp.contacts.index', ['branch' => $group->branch_id]) }}" class="small d-inline-block mt-2">Tambah / impor kontak baru</a>
                </div>
            </div>
        </div>
    </div>
@endsection
