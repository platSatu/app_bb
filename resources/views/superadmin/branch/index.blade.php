@extends('layouts.dashboard')

@section('title', 'Data User')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.user._tabs', ['active' => 'superadmin.branches'])

    @php($canManage = auth()->user()->canAccess('superadmin.branches', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="d-flex flex-wrap gap-2">
                    <select name="company" class="form-select" style="width: auto;" onchange="this.form.submit()">
                        <option value="">Semua perusahaan</option>
                        @foreach ($companies as $option)
                            <option value="{{ $option->id }}" @selected(request('company') === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group" style="max-width: 300px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama branch atau kota" value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                    </div>
                </form>
                @if ($canManage)
                    <a href="{{ route('superadmin.branches.create', ['company' => request('company')]) }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Branch
                    </a>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Branch</th>
                            <th>Perusahaan</th>
                            <th>Kota</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                            <tr>
                                <td class="fw-semibold">{{ $branch->name }}</td>
                                <td>{{ $branch->company?->name ?? '-' }}</td>
                                <td>{{ $branch->city ?: '-' }}</td>
                                <td class="text-nowrap">{{ $branch->handphone ? '+'.$branch->handphone : ($branch->phone ?: '-') }}</td>
                                <td>
                                    <span class="badge {{ $branch->isActive() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $branch->isActive() ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('superadmin.branches.edit', $branch) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <form action="{{ route('superadmin.branches.destroy', $branch) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus branch '.$branch->name.'? Tindakan ini tidak bisa dibatalkan.') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger">
                                                <i class="ri-delete-bin-line"></i> Hapus
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ request('search') || request('company') ? 'Tidak ada branch yang cocok dengan filter ini.' : 'Belum ada branch.' }}
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
