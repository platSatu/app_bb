@extends('layouts.dashboard')

@section('title', 'Edit User')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.users.update', $user) }}">
                @csrf
                @method('PUT')
                @include('superadmin.user._form')
            </form>
        </div>
    </div>
@endsection
