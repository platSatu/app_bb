@extends('layouts.dashboard')

@section('title', 'Edit Company')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.companies.update', $company) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('superadmin.company._form')
            </form>
        </div>
    </div>
@endsection
