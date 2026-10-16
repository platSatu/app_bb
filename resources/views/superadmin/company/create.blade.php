@extends('layouts.dashboard')

@section('title', 'Tambah Company')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.companies.store') }}" enctype="multipart/form-data">
                @csrf
                @include('superadmin.company._form')
            </form>
        </div>
    </div>
@endsection
