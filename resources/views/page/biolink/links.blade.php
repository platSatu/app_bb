@extends('layouts.dashboard')

@section('title', 'Bio Link')
@section('page-section', \App\Support\Apps::pageSection($routes))

@php($links = old('links', $page->setting('biolink.links', [])))

@section('content')
    @include('page.category._tabs', ['active' => 'biolink-links'])

    <form method="POST" action="{{ route($routes.'.biolink.update', $page) }}">
        @csrf
        @method('PUT')
        <div class="card" style="max-width: 900px;">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h6 class="mb-1 fw-semibold">Daftar Link</h6>
                    <div class="text-muted small lh-sm">Satu baris = satu tombol. Pilih page milik branch ini, atau isi link luar. Geser <i class="ri-draggable"></i> untuk mengurutkan, sakelar untuk sembunyikan.</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" name="color" value="{{ old('color', $page->setting('biolink.color', '#111827')) }}" class="form-control form-control-color" title="Warna tombol">
                    @if ($canManage)
                        <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Semua</button>
                    @endif
                </div>
            </div>
            <div class="card-body bg-light p-3 p-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">@foreach ($errors->all() as $message) <div>{{ $message }}</div> @endforeach</div>
                @endif
                <fieldset @disabled(! $canManage)>
                    <div data-sortable id="link-rows">
                        @foreach ($links as $i => $link)
                            @include('page.biolink._link-row')
                        @endforeach
                    </div>
                    <div id="link-empty" class="text-center text-muted py-4 {{ count($links) ? 'd-none' : '' }}">
                        <i class="ri-links-line fs-2 d-block mb-1"></i>Belum ada link. Klik Tambah Link.
                    </div>
                </fieldset>
                @if ($canManage)
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-link"><i class="ri-add-line"></i> Tambah Link</button>
                    <template id="link-template">@include('page.biolink._link-row', ['i' => '__INDEX__', 'link' => []])</template>
                @endif
                <div class="form-text mt-3">Judul & keterangan profil diambil dari Nama Page dan Deskripsi di tab Informasi. Foto profil memakai logo perusahaan.</div>
            </div>
        </div>
    </form>

    <script src="{{ asset('be/assets/js/sortable-rows.js') }}"></script>
    <script>
        (function () {
            var rows = document.getElementById('link-rows'), template = document.getElementById('link-template'), counter = 0;
            function refresh() { document.getElementById('link-empty').classList.toggle('d-none', rows.children.length > 0); }
            if (!template) return;
            document.getElementById('add-link').addEventListener('click', function () {
                if (rows.children.length >= 30) { alert('Maksimal 30 link.'); return; }
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
