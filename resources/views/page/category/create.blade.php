@extends('layouts.dashboard')

@php($noun = in_array($routes, ['events', 'superadmin.events'], true) ? 'Event' : 'Page')
@section('title', 'Buat '.$noun)
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Langkah 1 · Informasi {{ $noun }}</h5>
            <div class="text-muted small">{{ $noun === 'Event' ? 'Setelah disimpan, Anda lanjut mengisi kategori tiket, form pembeli, dan pengaturan event lainnya.' : 'Setelah disimpan, Anda bisa lanjut menyusun isi page sesuai kategorinya.' }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route($routes.'.store') }}" enctype="multipart/form-data">
                @csrf
                @include('page.category._form')
            </form>
        </div>
    </div>
@endsection
