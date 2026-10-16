@extends('layouts.dashboard')

@section('title', 'Tambah User')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <p class="text-muted">User baru langsung berstatus <strong>aktif</strong> dan bisa langsung masuk.</p>
            <form method="POST" action="{{ route('superadmin.users.store') }}">
                @csrf
                @include('superadmin.user._form')
            </form>
        </div>
    </div>
@endsection
