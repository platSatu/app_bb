@extends('layouts.dashboard')

@php
    $isAdmin = str_starts_with($routes, 'superadmin.');
    // Halaman ini dipakai menu Event (/events) dan Pages (/pages).
    $isEvent = in_array($routes, ['events', 'superadmin.events'], true);
    $noun = $isEvent ? 'Event' : 'Page';
@endphp
@section('title', 'List '.$noun)
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama atau link" value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-3">
                    <select name="type" class="form-select">
                        <option value="">Semua kategori</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(request('type') === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if (isset($branches) && $branches->count() > 1)
                    <div class="col-12 col-md-3">
                        <select name="branch" class="form-select">
                            <option value="">Semua branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch') === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'type', 'branch']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @if ($canManage)
                <div class="d-flex justify-content-end gap-2 mb-3">
                    @if ($isAdmin && $isEvent)
                        <a href="{{ route('superadmin.ticket-fee.edit') }}" class="btn btn-outline-primary"><i class="ri-percent-line"></i> Biaya Layanan Tiket</a>
                    @endif
                    <a href="{{ route($routes.'.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Buat {{ $noun }}</a>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>QR</th>
                            <th>{{ $noun }}</th>
                            <th>Kategori</th>
                            <th>{{ $isAdmin ? 'Perusahaan / Branch' : 'Branch' }}</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            <tr>
                                <td>
                                    @if ($page->qrcodeUrl())
                                        <a href="{{ route($routes.'.qrcode', $page) }}" title="Unduh QR code">
                                            <img src="{{ $page->qrcodeUrl() }}" alt="QR {{ $page->name }}" width="48" height="48" class="border rounded">
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $page->name }}</div>
                                    <div class="d-flex align-items-center gap-1 small">
                                        <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="text-truncate" style="max-width: 260px;">{{ $page->url() }}</a>
                                        <button type="button" class="btn btn-link btn-sm p-0" data-copy="{{ $page->url() }}" title="Salin link"><i class="ri-file-copy-line"></i></button>
                                    </div>
                                </td>
                                <td>{{ $page->type?->name ?? '-' }}</td>
                                <td>
                                    @if ($isAdmin)
                                        <div>{{ $page->branch?->company?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $page->branch?->name }}</div>
                                    @else
                                        {{ $page->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($page->start_date || $page->end_date)
                                        {{ $page->start_date?->translatedFormat('d M Y') ?? '...' }} – {{ $page->end_date?->translatedFormat('d M Y') ?? '...' }}
                                    @else
                                        <span class="text-muted">Tanpa batas</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $page->statusBadge() }}">{{ $page->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    @if ($canManage)
                                    <a href="{{ route($routes.'.edit', $page) }}" class="btn btn-sm rounded-pill btn-primary"><i class="ri-settings-3-line"></i> Setting</a>
                                    @endif
                                    @if ($quick = $page->templateConfig('quick'))
                                        <a href="{{ route($routes.'.'.$quick[2], $page) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="{{ $quick[1] }}"></i> {{ $quick[0] }} ({{ $page->{$quick[3]} ?? 0 }})</a>
                                    @endif
                                    <a href="{{ route($routes.'.qrcode', $page) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-qr-code-line"></i> QR</a>
                                    @if ($canManage)
                                    <a href="{{ route($routes.'.edit', $page) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                    <form action="{{ route($routes.'.destroy', $page) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus '.strtolower($noun).' '.$page->name.'? Link dan QR code-nya tidak bisa dipakai lagi.') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ request()->hasAny(['search', 'branch', 'type']) ? 'Tidak ada '.strtolower($noun).' yang cocok.' : 'Belum ada '.strtolower($noun).'. Yuk buat yang pertama.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $pages->links() }}</div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                navigator.clipboard.writeText(button.dataset.copy).then(function () {
                    button.innerHTML = '<i class="ri-check-line text-success"></i>';
                    setTimeout(function () { button.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 1500);
                });
            });
        });
    </script>
@endsection
