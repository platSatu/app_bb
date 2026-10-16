@extends('layouts.dashboard')

@section('title', 'Edit Field')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'builder'])

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Field · {{ $field->label }}</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route($routes.'.fields.update', [$page, $field]) }}">
                @csrf
                @method('PUT')
                @include('page.form._field-form')
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route($routes.'.builder', $page) }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
