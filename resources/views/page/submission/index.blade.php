@extends('layouts.dashboard')

@section('title', 'Data Masuk')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'submissions'])

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Data Masuk · {{ $page->name }}</h5>
            <div class="text-muted small">Total {{ $submissions->total() }} pendaftar</div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nomor referensi atau isi jawaban" value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request('search'))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>Ringkasan</th>
                            <th>Waktu Masuk</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissions as $submission)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $submission->reference }}</td>
                                <td class="small">
                                    @foreach ($submission->answers->take(3) as $answer)
                                        <div><span class="text-muted">{{ $answer->label }}:</span> {{ \Illuminate\Support\Str::limit($answer->value ?? $answer->file_name ?? '-', 60) }}</div>
                                    @endforeach
                                </td>
                                <td class="text-nowrap">{{ $submission->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route($routes.'.submissions.show', [$page, $submission]) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                        <i class="ri-eye-line"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    {{ request('search') ? 'Tidak ada data yang cocok.' : 'Belum ada pendaftar. Bagikan link atau QR code page ini.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $submissions->links() }}</div>
        </div>
    </div>
@endsection
