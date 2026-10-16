@extends('layouts.dashboard')

@section('title', 'Kategori Produk')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@php
    $rows = old('categories', $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'count' => $c->products_count])->all());
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'catalog-categories'])

    <form method="POST" action="{{ route($routes.'.catalog.categories.sync', $page) }}">
        @csrf
        @method('PUT')
        <div class="card" style="max-width: 760px;">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Kategori Produk</h6>
                    <div class="text-muted small lh-sm">Geser <i class="ri-draggable"></i> untuk mengurutkan. Kategori dihapus = produknya jadi tanpa kategori.</div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Semua</button>
                @endif
            </div>
            <div class="card-body bg-light p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <fieldset @disabled(! $canManage)>
                    <div data-sortable id="category-rows">
                        @foreach ($rows as $i => $row)
                            @include('page.catalog._category-row')
                        @endforeach
                    </div>
                </fieldset>
                @if ($canManage)
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-category"><i class="ri-add-line"></i> Tambah Kategori</button>
                    <template id="category-template">
                        @include('page.catalog._category-row', ['i' => '__INDEX__', 'row' => ['status' => 'active']])
                    </template>
                @endif
            </div>
        </div>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('category-rows'), template = document.getElementById('category-template'), counter = 0;
            if (!template) return;
            document.getElementById('add-category').addEventListener('click', function () {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.querySelector('input[type=text]').focus();
            });
            rows.addEventListener('click', function (event) {
                var button = event.target.closest('[data-remove]');
                if (button) button.closest('[data-row]').remove();
            });
        })();
    </script>
@endsection
