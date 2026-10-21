@extends('layouts.dashboard')

@section('title', 'Buat Page')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Langkah 1 · Informasi Page</h5>
            <div class="text-muted small">Setelah disimpan, Anda bisa lanjut menyusun isi page sesuai kategorinya.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route($routes.'.store') }}" enctype="multipart/form-data">
                @csrf
                @include('page.category._form')
            </form>
        </div>
    </div>
@endsection
