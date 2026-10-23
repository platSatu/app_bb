@extends('whatsapp.google-form.public.layout')

@section('title', 'Dokumentasi API WhatsApp')
@section('width', '980px')

@push('head')
    @include('whatsapp.third-party._docs-style')
@endpush

@section('content')
    <div class="bg-white rounded-4 border p-3 p-md-4 api-docs">{!! $html !!}</div>
@endsection
