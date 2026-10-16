@extends('layouts.dashboard')

@section('title', 'Tambah Branch')
@section('page-section', 'Settings')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.branches.store') }}">
                @csrf
                @include('settings.branch._form')
            </form>
        </div>
    </div>
@endsection
