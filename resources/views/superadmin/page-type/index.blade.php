@extends('layouts.dashboard')

@section('title', 'Category Page')
@section('page-section', 'Superadmin')

@section('content')
    @php($canManage = auth()->user()->canAccess('superadmin.page-types', \App\Support\Menu::ABILITY_MANAGE))

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="input-group" style="max-width: 360px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama kategori" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                </form>
                @if ($canManage)
                    <a href="{{ route('superadmin.page-types.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Category Page</a>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th>Template</th>
                            <th class="text-center">Dipakai</th>
                            <th>Status</th>
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($types as $category)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="h-36px w-36px rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
                                            @if ($category->imageUrl())
                                                <img src="{{ $category->imageUrl() }}" alt="" class="w-100 h-100" style="object-fit: contain;">
                                            @else
                                                <i class="ri-apps-line text-muted"></i>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $category->name }}</div>
                                            <div class="text-muted fs-12">{{ $category->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $category->templateLabel() }}</td>
                                <td class="text-center">
                                    <a href="{{ route(\App\Support\Apps::forTemplate($category->template) === 'events' ? 'superadmin.events.index' : 'superadmin.pages.index', ['type' => $category->id]) }}">{{ $category->pages_count }}</a>
                                </td>
                                <td><span class="badge {{ $category->statusBadge() }}">{{ $category->statusLabel() }}</span></td>
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('superadmin.page-types.edit', $category) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                        <form action="{{ route('superadmin.page-types.destroy', $category) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus kategori '.$category->name.'?') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    {{ request('search') ? 'Tidak ada kategori yang cocok dengan pencarian ini.' : 'Belum ada category page. Contoh: Pendaftaran, Landing Page, Katalog, Tiket, Jualan, Quiz.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $types->links() }}</div>
        </div>
    </div>
@endsection
