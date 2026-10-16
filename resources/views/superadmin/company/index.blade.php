@extends('layouts.dashboard')

@section('title', 'Data User')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.user._tabs', ['active' => 'superadmin.companies'])

    @php($canManage = auth()->user()->canAccess('superadmin.companies', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="input-group" style="max-width: 360px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama perusahaan atau pemilik" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                </form>
                @if ($canManage)
                    <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Company
                    </a>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Perusahaan</th>
                            <th>Pemilik</th>
                            <th>Kontak</th>
                            <th class="text-center">Branch</th>
                            <th class="text-center">Limit</th>
                            <th>Status</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="h-36px w-36px rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
                                            @if ($company->logoUrl())
                                                <img src="{{ $company->logoUrl() }}" alt="" class="w-100 h-100" style="object-fit: contain;">
                                            @else
                                                <i class="ri-building-line text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $company->name }}</div>
                                            <div class="text-muted fs-12">{{ $company->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $company->user?->name ?? '-' }}</div>
                                    <div class="text-muted fs-12">{{ $company->user?->email }}</div>
                                </td>
                                <td class="text-nowrap">
                                    {{ $company->handphone ? '+'.$company->handphone : ($company->phone ?: '-') }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('superadmin.branches.index', ['company' => $company->id]) }}">{{ $company->branches_count }}</a>
                                </td>
                                <td class="text-center">{{ $company->limit ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $company->status === \App\Models\UserCompany::STATUS_ACTIVE ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $company->status === \App\Models\UserCompany::STATUS_ACTIVE ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('superadmin.companies.edit', $company) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <form action="{{ route('superadmin.companies.destroy', $company) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus perusahaan '.$company->name.'? Semua branch-nya ('.$company->branches_count.') ikut terhapus dan tidak bisa dibatalkan.') }});">
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ request('search') ? 'Tidak ada perusahaan yang cocok dengan pencarian ini.' : 'Belum ada perusahaan.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $companies->links() }}</div>
        </div>
    </div>
@endsection
