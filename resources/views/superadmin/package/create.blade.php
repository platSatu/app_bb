@extends('layouts.dashboard')

@section('title', 'Tambah Package')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.packages.store') }}">
                @csrf
                @include('superadmin.package._form')
            </form>
        </div>
    </div>
@endsection
