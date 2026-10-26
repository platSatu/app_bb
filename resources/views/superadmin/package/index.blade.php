@extends('layouts.dashboard')

@section('title', 'Package')
@section('page-section', 'Superadmin')

@section('content')
    @php($canManage = auth()->user()->canAccess('superadmin.packages', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="search" class="form-control" style="width: 260px; max-width: 100%;" placeholder="Cari nama package" value="{{ request('search') }}">
                    <select name="category" class="form-select" style="width: 220px; max-width: 100%;">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                </form>
                @if ($canManage)
                    <a href="{{ route('superadmin.packages.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Package</a>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Package</th>
                            <th>Kategori & Layanan</th>
                            <th class="text-end">Harga</th>
                            <th class="text-center">Durasi</th>
                            <th>Fitur</th>
                            <th>Status</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($packages as $package)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $package->name }}</div>
                                    @if ($package->is_trial) <span class="badge bg-info-subtle text-info">Trial</span> @endif
                                    @if ($package->is_featured) <span class="badge bg-warning-subtle text-warning">Populer</span> @endif
                                </td>
                                <td>
                                    <div>{{ $package->category?->name ?? '-' }}</div>
                                    @forelse ($package->appLabels() as $label)
                                        <span class="badge bg-primary-subtle text-primary">{{ $label }}</span>
                                    @empty
                                        <span class="badge bg-danger-subtle text-danger" title="Edit paket ini dan pilih layanannya">Belum pilih layanan</span>
                                    @endforelse
                                </td>
                                <td class="text-end text-nowrap">Rp {{ number_format($package->price, 0, ',', '.') }}</td>
                                <td class="text-center text-nowrap">{{ $package->duration }} hari</td>
                                <td>
                                    @if ($features = $package->features())
                                        <ul class="mb-0 ps-3 small">
                                            @foreach (array_slice($features, 0, 3) as $feature)
                                                <li>{{ $feature }}</li>
                                            @endforeach
                                        </ul>
                                        @if (count($features) > 3)
                                            <div class="text-muted small">+{{ count($features) - 3 }} lainnya</div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $package->statusBadge() }}">{{ $package->statusLabel() }}</span></td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('superadmin.packages.edit', $package) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        {{-- Paket yang sudah dibeli/dipakai ditolak saat dihapus (pesan dari controller): nonaktifkan lewat Edit. --}}
                                        <form action="{{ route('superadmin.packages.destroy', $package) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus package '.$package->name.'?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ request()->hasAny(['search', 'category']) ? 'Tidak ada package yang cocok.' : 'Belum ada package.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $packages->links() }}</div>
        </div>
    </div>
@endsection
