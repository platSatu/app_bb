@extends('layouts.dashboard')

@section('title', 'Atur Kategori Tiket')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@php
    $fmt = fn ($date) => $date?->format('Y-m-d\TH:i');
    $rows = old('categories', $categories->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'price' => $c->price, 'quota' => $c->quota,
        'max_per_order' => $c->max_per_order, 'sale_starts_at' => $fmt($c->sale_starts_at), 'sale_ends_at' => $fmt($c->sale_ends_at), 'status' => $c->status,
    ])->all());
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-manage'])

    <form method="POST" action="{{ route($routes.'.ticket.categories.sync', $page) }}">
        @csrf
        @method('PUT')
        <div class="card">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Atur Kategori Tiket</h6>
                    <div class="text-muted small lh-sm">Tentukan jenis tiket yang dijual, mis. VIP, Reguler, atau Early Bird. Geser <i class="ri-draggable"></i> untuk mengubah urutan tampil. Kategori yang sudah ada pembelinya tidak bisa dihapus.</div>
                </div>
                @if ($canManage)
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Perubahan</button>
                @endif
            </div>
            <div class="card-body bg-light p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach
                    </div>
                @endif
                <fieldset @disabled(! $canManage)>
                    <div data-sortable id="ticket-rows">
                        @foreach ($rows as $i => $row)
                            @include('page.ticket._category-row')
                        @endforeach
                    </div>
                    <div id="ticket-empty" class="text-center text-muted py-4 {{ count($rows) ? 'd-none' : '' }}">
                        <i class="ri-coupon-3-line fs-2 d-block mb-1"></i>Belum ada kategori tiket. Klik tombol Tambah Kategori Tiket di bawah.
                    </div>
                </fieldset>
                @if ($canManage)
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-ticket"><i class="ri-add-line"></i> Tambah Kategori Tiket</button>
                    <template id="ticket-template">@include('page.ticket._category-row', ['i' => '__INDEX__', 'row' => []])</template>
                @endif
            </div>
        </div>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('ticket-rows'), template = document.getElementById('ticket-template'), counter = 0;
            function refresh() { document.getElementById('ticket-empty').classList.toggle('d-none', rows.children.length > 0); }
            if (!template) return;
            document.getElementById('add-ticket').addEventListener('click', function () {
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.querySelector('input[type=text]').focus();
                refresh();
            });
            rows.addEventListener('click', function (event) {
                if (!event.target.closest('[data-remove]')) return;
                event.target.closest('[data-row]').remove();
                refresh();
            });
        })();
    </script>
@endsection
