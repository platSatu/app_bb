@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">Halo, {{ Auth::user()->name }}</h5>
            <p class="text-muted mb-0">Selamat datang di {{ config('app.name') }}.</p>
        </div>
    </div>
@endsection
