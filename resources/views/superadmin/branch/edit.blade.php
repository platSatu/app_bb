@extends('layouts.dashboard')

@section('title', 'Edit Branch')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.branches.update', $branch) }}">
                @csrf
                @method('PUT')
                @include('superadmin.branch._form')
            </form>
        </div>
    </div>
@endsection
