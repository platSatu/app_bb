@extends('layouts.dashboard')

@php($noun = in_array($routes, ['events', 'superadmin.events'], true) ? 'Event' : 'Page')
@section('title', 'Edit '.$noun)
@section('page-section', \App\Support\Apps::pageSection($routes))

@section('content')
    @include('page.category._tabs', ['active' => 'info'])

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Informasi {{ $noun }}</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route($routes.'.update', $page) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('page.category._form')
            </form>
        </div>
    </div>
@endsection
