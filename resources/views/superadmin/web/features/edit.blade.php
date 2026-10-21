@extends('layouts.dashboard')

@section('title', 'Edit Fitur')
@section('page-section', 'Website')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-4">Edit Fitur</h4>

                    <form action="{{ route('superadmin.web.features.update', $feature->id) }}" method="POST" enctype="multipart/form-data">
                        @include('superadmin.web.features._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
