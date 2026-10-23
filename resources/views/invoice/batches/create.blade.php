@extends('layouts.dashboard')

@section('title', 'Buat Invoice Periode')
@section('page-section', 'Invoice')

@section('content')
    @include('invoice._tabs')

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Buat Invoice Periode</h5>
            <div class="text-muted small">Satu invoice dibuat otomatis untuk setiap customer aktif di kategori. Nominal khusus customer dipakai kalau ada.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('invoices.batches.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="category_id" class="form-label">Kategori</label>
                        <select id="category_id" name="category_id" class="form-select" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id', request('category')) === $category->id)>{{ $category->name }} ({{ $category->subscriptions_count }} customer · {{ $category->lateFeeSummary() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama Periode</label>
                        <input type="text" id="name" name="name" maxlength="150" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="mis. Iuran {{ now()->translatedFormat('F Y') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="amount" class="form-label">Nominal per Customer</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" id="amount" name="amount" data-rupiah class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="due_date" class="form-label">Jatuh Tempo</label>
                        <input type="date" id="due_date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}" required>
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="use_late_fee" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="use_late_fee" name="use_late_fee" value="1" @checked(old('use_late_fee', true))>
                            <label class="form-check-label" for="use_late_fee">Pakai denda kategori</label>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('invoices.batches.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Buat Invoice</button>
                </div>
            </form>
        </div>
    </div>
@endsection
