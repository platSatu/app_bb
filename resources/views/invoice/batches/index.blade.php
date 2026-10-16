@extends('layouts.dashboard')

@section('title', 'Invoice Periode')
@section('page-section', 'Invoice')

@php($rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.'))

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-nowrap align-items-end gap-2 overflow-auto pb-2 mb-3">
                <div class="flex-grow-1" style="min-width: 200px">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Nama periode" value="{{ request('search') }}">
                </div>
                <div style="min-width: 170px">
                    <label class="form-label small text-muted mb-1">Kategori</label>
                    <select name="category" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category') === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1 text-nowrap">Jatuh tempo dari</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div style="min-width: 160px">
                    <label class="form-label small text-muted mb-1">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="submit" class="btn btn-outline-secondary text-nowrap"><i class="ri-search-line"></i> Filter</button>
                    @if (request()->hasAny(['search', 'category', 'date_from', 'date_to']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                    <a href="{{ route('invoices.batches.create') }}" class="btn btn-primary text-nowrap"><i class="ri-add-line"></i> Buat Periode</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Periode</th><th>Kategori</th><th>Jatuh Tempo</th><th class="text-end">Nominal</th><th>Lunas</th><th class="text-end">Terkumpul</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php($percent = $item->invoices_count ? round($item->paid_count / $item->invoices_count * 100) : 0)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->category?->name }}</td>
                                <td class="text-nowrap {{ ! $item->isCancelled() && $item->due_date->isPast() && $percent < 100 ? 'text-danger' : '' }}">{{ $item->due_date->translatedFormat('d M Y') }}</td>
                                <td class="text-end">{{ $rp($item->amount) }}</td>
                                <td style="min-width: 140px">
                                    <div class="small">{{ $item->paid_count }} / {{ $item->invoices_count }}</div>
                                    <div class="progress" style="height: 6px"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>
                                </td>
                                <td class="text-end">{{ $rp($item->paid_total) }}</td>
                                <td><span class="badge {{ $item->isCancelled() ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">{{ $item->isCancelled() ? 'Dibatalkan' : 'Aktif' }}</span></td>
                                <td class="text-end"><a href="{{ route('invoices.batches.show', $item) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada periode. Buat periode pertama, mis. "Iuran Oktober 2026".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
