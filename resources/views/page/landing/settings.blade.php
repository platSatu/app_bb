@extends('layouts.dashboard')

@section('title', 'Menu & Tampilan')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php
    $menu = old('menu', $page->setting('landing.menu', []));
    $logo = $page->setting('landing.logo');
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'landing-settings'])

    <form method="POST" action="{{ route($routes.'.landing.settings.update', $page) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
        @endif
        <fieldset @disabled(! $canManage)>
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Tampilan</h6></div>
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="color">Warna utama</label>
                                    <input type="color" id="color" name="color" value="{{ old('color', $page->setting('landing.color', '#4f46e5')) }}" class="form-control form-control-color w-100">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="font">Font</label>
                                    <select id="font" name="font" class="form-select">
                                        @foreach (array_keys(\App\Support\LandingNavigation::FONTS) as $font)
                                            <option value="{{ $font }}" @selected(old('font', $page->setting('landing.font', 'Inter')) === $font)>{{ $font }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="logo">Logo (opsional)</label>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($logo)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="" height="40" class="border rounded p-1">
                                            <label class="small text-nowrap"><input type="checkbox" name="remove_logo" value="1"> Hapus</label>
                                        @endif
                                        <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" class="form-control">
                                    </div>
                                    <div class="form-text">Kosong = pakai logo perusahaan.</div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="is_home" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_home" name="is_home" value="1" @checked(old('is_home', $page->setting('landing.is_home', false)))>
                                        <label class="form-check-label fw-semibold" for="is_home">Jadikan Beranda branch {{ $page->branch?->name }}</label>
                                    </div>
                                    <div class="form-text">Menu & warna Beranda ikut tampil di page tujuan menu (Toko, Quiz, Pendaftaran...), jadi terasa satu website. Satu branch hanya punya satu Beranda.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header bg-white border-bottom px-4 py-3">
                            <h6 class="mb-1 fw-semibold">Menu Atas</h6>
                            <div class="text-muted small lh-sm">Pilih page milik branch ini, atau isi link luar (mis. Instagram). Geser <i class="ri-draggable"></i> untuk mengurutkan.</div>
                        </div>
                        <div class="card-body bg-light p-3 p-md-4">
                            <div data-sortable id="menu-rows">
                                @foreach ($menu as $i => $item)
                                    @include('page.landing._menu-row')
                                @endforeach
                            </div>
                            @if ($canManage)
                                <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-menu"><i class="ri-add-line"></i> Tambah Menu</button>
                                <template id="menu-template">@include('page.landing._menu-row', ['i' => '__INDEX__', 'item' => []])</template>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="d-flex justify-content-end mt-1"><button type="submit" class="btn btn-primary">Simpan</button></div>
            @endif
        </fieldset>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('menu-rows'), template = document.getElementById('menu-template'), counter = 0;
            if (!template) return;
            document.getElementById('add-menu').addEventListener('click', function () {
                if (rows.children.length >= 10) { alert('Maksimal 10 menu.'); return; }
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++)));
                rows.lastElementChild.querySelector('input').focus();
            });
            rows.addEventListener('click', function (event) {
                if (event.target.closest('[data-remove]')) event.target.closest('[data-row]').remove();
            });
        })();
    </script>
@endsection
