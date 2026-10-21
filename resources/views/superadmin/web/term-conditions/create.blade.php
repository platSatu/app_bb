@extends('layouts.dashboard')

@section('title', 'Tambah Syarat dan Ketentuan')
@section('page-section', 'Website')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Syarat & Ketentuan</h4>

                    <form action="{{ route('superadmin.web.term-conditions.store') }}" method="POST">
                        @include('superadmin.web.term-conditions._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
