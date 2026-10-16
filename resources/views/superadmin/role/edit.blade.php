@extends('layouts.dashboard')

@section('title', 'Edit Role')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.roles.update', $role) }}">
                @csrf
                @method('PUT')
                @include('superadmin.role._form')
            </form>
        </div>
    </div>
@endsection
