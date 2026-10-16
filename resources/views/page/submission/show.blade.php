@extends('layouts.dashboard')

@section('title', 'Detail Pendaftar')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'submissions'])

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h5 class="mb-0">{{ $submission->reference }}</h5>
                <div class="text-muted small">Masuk {{ $submission->created_at->translatedFormat('d F Y, H:i') }}</div>
            </div>
            <a href="{{ route($routes.'.submissions.index', $page) }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
        <div class="card-body">
            @if ($submission->customer)
                @php($contactRoute = $routes === 'pages' ? 'contacts.show' : 'superadmin.contacts.show')
                <div class="alert alert-light border d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div><i class="ri-contacts-book-line me-1"></i> Tersimpan di Buku Kontak: <strong>{{ $submission->customer->displayName() }}</strong></div>
                    @if (auth()->user()->canAccess($routes === 'pages' ? 'contacts.list' : 'superadmin.customers'))
                        <a href="{{ route($contactRoute, $submission->customer) }}" class="btn btn-sm btn-outline-secondary">Lihat Kontak</a>
                    @endif
                </div>
            @endif

            <dl class="row mb-0">
                @foreach ($submission->answers as $answer)
                    <dt class="col-sm-4 text-muted fw-normal">{{ $answer->label }}</dt>
                    <dd class="col-sm-8">
                        @if ($answer->file_path)
                            <a href="{{ route($routes.'.submissions.file', [$page, $submission, $answer]) }}"><i class="ri-download-2-line"></i> {{ $answer->file_name }}</a>
                        @elseif (filled($answer->value))
                            <span style="white-space: pre-line;">{{ $answer->value }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </dd>
                @endforeach
            </dl>
        </div>
    </div>
@endsection
