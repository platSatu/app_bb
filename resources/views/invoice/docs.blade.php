@extends('layouts.dashboard')

@section('title', 'Dokumentasi API Invoice')
@section('page-section', 'Invoice')

@push('styles')
    <style>
        .api-docs { max-width: 920px; line-height: 1.7; }
        .api-docs h1 { font-size: 1.6rem; } .api-docs h2 { font-size: 1.25rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--bs-border-color); }
        .api-docs h3 { font-size: 1.05rem; margin-top: 1.5rem; }
        .api-docs pre { background: #0f172a; color: #e2e8f0; padding: 1rem; border-radius: .5rem; overflow-x: auto; font-size: .82rem; line-height: 1.5; }
        .api-docs code { font-size: .85em; } .api-docs pre code { color: inherit; }
        .api-docs table { width: 100%; margin-bottom: 1rem; font-size: .9rem; } .api-docs th, .api-docs td { border: 1px solid var(--bs-border-color); padding: .4rem .6rem; vertical-align: top; }
        .api-docs th { background: var(--bs-light); } .api-docs blockquote { border-left: 4px solid var(--bs-warning); padding: .5rem 1rem; background: var(--bs-warning-bg-subtle); }
    </style>
@endpush

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-body api-docs">{!! $html !!}</div>
    </div>
@endsection
