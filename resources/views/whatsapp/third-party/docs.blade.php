@extends('layouts.dashboard')

@section('title', 'Dokumentasi API WhatsApp')
@section('page-section', 'WhatsApp')

@push('styles')
    @include('whatsapp.third-party._docs-style')
@endpush

@section('content')
    <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2">
        <i class="ri-share-line text-primary"></i>
        <span class="small flex-grow-1">Link publik untuk developer Anda (tanpa login, tanpa data akun):</span>
        <div class="input-group input-group-sm" style="max-width: 420px;">
            <input type="text" class="form-control font-monospace" value="{{ route('docs.whatsapp-api') }}" readonly>
            <button class="btn btn-outline-secondary" type="button" data-copy="{{ route('docs.whatsapp-api') }}"><i class="ri-file-copy-line"></i></button>
        </div>
    </div>

    <div class="card">
        <div class="card-body api-docs">{!! $html !!}</div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-common.js') }}"></script>
@endpush
