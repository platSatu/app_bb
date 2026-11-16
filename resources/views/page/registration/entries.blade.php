@extends('layouts.dashboard')

@section('title', 'Pendaftar')
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'registration-entries'])

    @php($fields = $page->fields)

    <div class="card">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-semibold">Pendaftar</h6>
                <div class="text-muted small lh-sm">Jawaban yang masuk lewat halaman publik. Kolomnya mengikuti Form Pendaftaran.</div>
            </div>
            <a href="{{ route($routes.'.registration.entries.export', ['page' => $page] + request()->only('search')) }}" class="btn btn-success btn-sm"><i class="ri-file-excel-2-line"></i> Export Excel</a>
        </div>

        <div class="card-body p-3 p-md-4">
            <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 420px;">
                <input type="text" name="search" class="form-control" maxlength="100" value="{{ request('search') }}" placeholder="Cari nama, nomor, atau referensi">
                <button class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                @if (request('search'))
                    <a href="{{ route($routes.'.registration.entries.index', $page) }}" class="btn btn-outline-danger" title="Hapus pencarian"><i class="ri-close-line"></i></a>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-nowrap">
                            <th>Referensi</th>
                            <th>Waktu Daftar</th>
                            @foreach ($fields as $field)
                                <th>{{ $field->label }}</th>
                            @endforeach
                            @if ($canManage) <th class="text-end">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            @php($answers = $entry->answers->keyBy('page_form_field_id'))
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ $entry->reference }}</td>
                                <td class="text-nowrap">
                                    {{ $entry->created_at?->format('d/m/Y') }}
                                    <div class="small text-muted">{{ $entry->created_at?->format('H:i') }}</div>
                                </td>
                                @foreach ($fields as $field)
                                    @php($answer = $answers->get($field->id))
                                    <td>
                                        @if ($answer?->file_path)
                                            {{-- Berkas pendaftar ada di disk privat, jadi hanya bisa diunduh lewat route yang dicek izinnya. --}}
                                            <a href="{{ route($routes.'.submissions.file', [$page, $entry, $answer]) }}"><i class="ri-download-2-line"></i> {{ $answer->file_name ?: 'Berkas' }}</a>
                                        @else
                                            {{ $answer?->value ?: '-' }}
                                        @endif
                                    </td>
                                @endforeach
                                @if ($canManage)
                                    <td class="text-end text-nowrap">
                                        <form action="{{ route($routes.'.registration.entries.destroy', [$page, $entry]) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus pendaftar '.$entry->reference.'? Jawaban dan berkasnya ikut terhapus dan tidak bisa dikembalikan.') }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $fields->count() + ($canManage ? 3 : 2) }}" class="text-center text-muted py-4">
                                    Belum ada yang mendaftar. Bagikan linknya: <a href="{{ $page->url() }}" target="_blank" rel="noopener">{{ $page->url() }}</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $entries->links() }}</div>
        </div>
    </div>
@endsection
