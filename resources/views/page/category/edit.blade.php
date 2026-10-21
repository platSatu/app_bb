@extends('layouts.dashboard')

@section('title', 'Edit Page')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@section('content')
    @include('page.category._tabs', ['active' => 'info'])

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Informasi Page</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route($routes.'.update', $page) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('page.category._form')
            </form>
        </div>
    </div>
@endsection
