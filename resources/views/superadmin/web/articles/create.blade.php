@extends('layouts.dashboard')

@section('title', 'Tambah Artikel')
@section('page-section', 'Website')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Tambah Artikel</h4>

                    <form action="{{ route('superadmin.web.articles.store') }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.articles._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
