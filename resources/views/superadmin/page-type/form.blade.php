@extends('layouts.dashboard')

@section('title', $type->exists ? 'Edit Category Page' : 'Tambah Category Page')
@section('page-section', 'Superadmin')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $type->exists ? route('superadmin.page-types.update', $type) : route('superadmin.page-types.store') }}" enctype="multipart/form-data">
                @csrf
                @if ($type->exists) @method('PUT') @endif

                <div class="mb-4">
                    <label for="template" class="form-label">Template</label>
                    <select id="template" name="template" class="form-select @error('template') is-invalid @enderror" required>
                        @foreach (\App\Models\PageType::TEMPLATES as $value => $label)
                            <option value="{{ $value }}" @selected(old('template', $type->template) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Menentukan isi page yang dibuat user. Nama kategori boleh apa saja, sistem membaca dari template ini.</div>
                    @error('template') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @include('superadmin.category-application._form', [
                    'category' => $type,
                    'indexRoute' => 'superadmin.page-types.index',
                ])
            </form>
        </div>
    </div>
@endsection
