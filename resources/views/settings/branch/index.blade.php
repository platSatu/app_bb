@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
    @include('settings._tabs', ['active' => 'branch'])

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama branch atau kota" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                </form>
                <a href="{{ route('settings.branches.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Branch
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Kota</th>
                            <th>Alamat</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr>
                                <td class="fw-semibold">{{ $branch->name }}</td>
                                <td>{{ $branch->city ?: '-' }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($branch->address, 50) ?: '-' }}</td>
                                <td class="text-nowrap">
                                    @if ($branch->handphone)
                                        <div><i class="ri-whatsapp-line text-success"></i> +{{ $branch->handphone }}</div>
                                    @endif
                                    @if ($branch->phone)
                                        <div><i class="ri-phone-line text-muted"></i> {{ $branch->phone }}</div>
                                    @endif
                                    @if (! $branch->handphone && ! $branch->phone) - @endif
                                </td>
                                <td>
                                    <span class="badge {{ $branch->isActive() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $branch->isActive() ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('settings.branches.edit', $branch) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                        <i class="ri-edit-line"></i> Edit
                                    </a>
                                    <form action="{{ route('settings.branches.destroy', $branch) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus branch '.$branch->name.'? Tindakan ini tidak bisa dibatalkan.') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ request('search') ? 'Tidak ada branch yang cocok dengan pencarian ini.' : 'Belum ada branch. Klik "Tambah Branch" untuk mulai.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $branches->links() }}</div>
        </div>
    </div>
@endsection
