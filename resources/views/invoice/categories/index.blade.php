@extends('layouts.dashboard')

@section('title', 'Kategori Invoice')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari kategori" value="{{ request('search') }}">
                </div>
                @if ($branches->count() > 1)
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
                    <a href="{{ route('invoices.categories.create') }}" class="btn btn-primary"><i class="ri-add-line"></i> Tambah Kategori</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th>Prefix</th>
                            <th>Denda</th>
                            <th>Email</th>
                            @if ($branches->count() > 1) <th>Branch</th> @endif
                            <th class="text-center">Customer</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    @if ($item->description) <div class="text-muted small text-truncate" style="max-width: 260px">{{ $item->description }}</div> @endif
                                </td>
                                <td><code>{{ $item->prefix }}</code></td>
                                <td class="small">{{ $item->lateFeeSummary() }}</td>
                                <td class="small">
                                    @if ($item->email_enabled)
                                        <i class="ri-mail-check-line text-success"></i> Aktif
                                        @if ($item->reminder_days) <div class="text-muted">Pengingat H-{{ implode(', H-', $item->reminder_days) }}</div> @endif
                                    @else
                                        <span class="text-muted">Tidak</span>
                                    @endif
                                </td>
                                @if ($branches->count() > 1) <td>{{ $item->branch?->name }}</td> @endif
                                <td class="text-center">
                                    <a href="{{ route('invoices.categories.customers', $item) }}">{{ $item->subscriptions_count }}</a>
                                </td>
                                <td><span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('invoices.batches.create', ['category' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-add-line"></i> Periode</a>
                                    <a href="{{ route('invoices.categories.customers', $item) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-user-add-line"></i> Customer</a>
                                    <a href="{{ route('invoices.categories.edit', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-edit-line"></i> Edit</a>
                                    <form action="{{ route('invoices.categories.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus kategori '.$item->name.'?') }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i> Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada kategori. Contoh: "Iuran Bulanan", "SPP", atau "Sewa Kios".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
