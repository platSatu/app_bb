@extends('layouts.dashboard')

@section('title', ($item->exists ? 'Edit ' : 'Tambah ').$config['label'])
@section('page-section', $isAdmin ? 'Superadmin' : 'Schedules')

@php($parent = isset($config['parent']) ? $resources[$config['parent']] : null)

@section('content')
    @include('schedule._tabs')

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">{{ $item->exists ? 'Edit '.$config['label'].' '.$item->name : 'Tambah '.$config['label'] }}</h5>
            @if ($item->exists && $child)
                <a href="{{ route($routes.'.'.$child.'.create', ['parent' => $item->id]) }}" class="btn btn-sm btn-outline-success">
                    <i class="ri-add-line"></i> {{ $resources[$child]['label'] }}
                </a>
            @endif
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $item->exists ? route($routes.'.'.$resource.'.update', $item) : route($routes.'.'.$resource.'.store') }}">
                @csrf
                @if ($item->exists) @method('PUT') @endif

                <div class="row g-3">
                    @if ($parent)
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label">{{ $parent['label'] }}</label>
                            <select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror" required>
                                <option value="">-- Pilih {{ strtolower($parent['label']) }} --</option>
                                @foreach ($parentOptions as $option)
                                    <option value="{{ $option->id }}" @selected(old('parent_id', $item->{$config['key']}) === $option->id)>
                                        {{ $option->label() }}{{ $parentOptions->pluck('branch_id')->unique()->count() > 1 ? ' — '.$option->branch?->name : '' }}{{ $option->isActive() ? '' : ' (tidak aktif)' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @elseif (! $item->exists)
                        <div class="col-md-6">
                            <label for="branch_id" class="form-label">Branch</label>
                            <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required data-branch-select>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>
                                        {{ $branch->company ? $branch->company->name.' — ' : '' }}{{ $branch->name }}{{ $branch->status === 'active' ? '' : ' (tidak aktif)' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama {{ $config['label'] }}</label>
                        <input type="text" id="name" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $item->name) }}" placeholder="{{ $config['placeholder'] }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @includeIf('schedule.'.$resource.'._fields')

                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach ($item::STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Keterangan (opsional)</label>
                        <textarea id="description" name="description" rows="3" maxlength="1000" class="form-control @error('description') is-invalid @enderror">{{ old('description', $item->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route($routes.'.'.$resource.'.index', array_filter(['parent' => isset($config['key']) ? $item->{$config['key']} : null])) }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
