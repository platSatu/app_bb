@extends('layouts.dashboard')

@section('title', 'Tambah Kategori Aplikasi')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.category-applications.store') }}" enctype="multipart/form-data">
                @csrf
                @include('superadmin.category-application._form')
            </form>
        </div>
    </div>
@endsection
