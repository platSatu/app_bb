@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
    @include('settings._tabs', ['active' => 'company'])

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @include('settings.company._fields')

                <div class="d-flex justify-content-end gap-2 mt-4">
                    @if ($company->exists)
                        <a href="{{ route('settings.company.show') }}" class="btn btn-light">Batal</a>
                    @endif
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

@endsection
