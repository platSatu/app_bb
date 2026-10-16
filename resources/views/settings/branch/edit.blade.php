@extends('layouts.dashboard')

@section('title', 'Edit Branch')
@section('page-section', 'Settings')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.branches.update', $branch) }}">
                @csrf
                @method('PUT')
                @include('settings.branch._form')
            </form>
        </div>
    </div>
@endsection
