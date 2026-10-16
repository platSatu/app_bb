@extends('layouts.dashboard')

@section('title', 'Edit Kategori Aplikasi')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.category-applications.update', $category) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('superadmin.category-application._form')
            </form>
        </div>
    </div>
@endsection
