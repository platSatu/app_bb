@extends('layouts.dashboard')

@section('title', 'Role')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <p class="text-muted mb-0">Role menentukan menu apa saja yang bisa dibuka staff.</p>
                <a href="{{ route('superadmin.roles.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Role
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Role</th>
                            <th>Keterangan</th>
                            <th class="text-center">Jumlah Menu</th>
                            <th class="text-center">Jumlah Staff</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td class="fw-semibold">{{ $role->name }}</td>
                                <td class="text-muted">{{ $role->description ?: '-' }}</td>
                                <td class="text-center">{{ $role->permissions_count }}</td>
                                <td class="text-center">{{ $role->users_count }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('superadmin.roles.edit', $role) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                        <i class="ri-edit-line"></i> Edit
                                    </a>
                                    <form action="{{ route('superadmin.roles.destroy', $role) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus role '.$role->name.'? Staff yang memakai role ini akan kehilangan akses menunya.') }});">
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
                                <td colspan="5" class="text-center text-muted py-4">Belum ada role. Buat role pertama untuk tim Anda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $roles->links() }}</div>
        </div>
    </div>
@endsection
