@extends('layouts.dashboard')

@section('title', 'Edit Package')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.packages.update', $package) }}">
                @csrf
                @method('PUT')
                @include('superadmin.package._form')
            </form>
        </div>
    </div>
@endsection
