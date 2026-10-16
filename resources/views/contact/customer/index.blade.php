@extends('layouts.dashboard')

@section('title', 'Buku Kontak')
@section('page-section', $routes['contacts'] === 'contacts' ? 'Kontak' : 'Superadmin')

@section('content')
    @php($isAdmin = $routes['contacts'] !== 'contacts')

    <div class="card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, WhatsApp, atau email" value="{{ request('search') }}">
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
                    @if (request()->hasAny(['search', 'branch']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>{{ $isAdmin ? 'Perusahaan / Branch' : 'Branch' }}</th>
                            <th class="text-center">Pendaftaran</th>
                            <th>Terakhir</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="fw-semibold">{{ $customer->name ?: '-' }}</td>
                                <td class="text-nowrap">{{ $customer->phone ? '+'.$customer->phone : '-' }}</td>
                                <td>{{ $customer->email ?: '-' }}</td>
                                <td>
                                    @if ($isAdmin)
                                        <div>{{ $customer->company?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $customer->branch?->name }}</div>
                                    @else
                                        {{ $customer->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-center">{{ $customer->submissions_count }}</td>
                                <td class="text-nowrap small">{{ $customer->last_seen_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route($routes['contacts'].'.show', $customer) }}" class="btn btn-sm rounded-pill btn-outline-secondary"><i class="ri-eye-line"></i> Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ request()->hasAny(['search', 'branch']) ? 'Tidak ada kontak yang cocok.' : 'Belum ada kontak. Kontak otomatis masuk saat ada yang mengisi form page Anda.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $customers->links() }}</div>
        </div>
    </div>
@endsection
