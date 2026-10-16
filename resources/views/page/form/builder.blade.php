@extends('layouts.dashboard')

@section('title', 'Form Builder')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@php
    $rows = old('fields', $page->fields->map(fn ($f) => $f->only(['id', 'type', 'label', 'placeholder', 'help_text', 'options', 'is_required', 'contact_field']))->all());
@endphp

@section('content')
    @include('page.category._tabs', ['active' => 'builder'])

    <form method="POST" action="{{ route($routes.'.builder.sync', $page) }}" id="builder-form">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h6 class="mb-1 fw-semibold">Langkah 2 · Susun Field</h6>
                            <div class="text-muted small lh-sm">Urutan baris = urutan di form. Klik Simpan Semua setelah selesai.</div>
                        </div>
                        @if ($canManage)
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="ri-save-line me-1"></i>Simpan Semua</button>
                        @endif
                    </div>
                    <div class="card-body bg-light p-3 p-md-4">
                        @error('fields') <div class="alert alert-danger">{{ $message }}</div> @enderror

                        <fieldset @disabled(! $canManage)>
                            <div id="rows">
                                @foreach ($rows as $i => $row)
                                    @include('page.form._row', ['i' => $i, 'row' => $row])
                                @endforeach
                            </div>

                            <div id="rows-empty" class="text-center text-muted py-4 {{ count($rows) ? 'd-none' : '' }}">
                                <i class="ri-list-check-2 fs-2 d-block mb-1"></i>
                                Belum ada field. Klik Tambah Baris, mis. Nama Lengkap dan WhatsApp.
                            </div>
                        </fieldset>

                        @if ($canManage)
                            <button type="button" class="btn btn-outline-primary w-100 mt-2" id="add-row">
                                <i class="ri-add-line"></i> Tambah Baris
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card position-sticky" style="top: 90px;">
                    <div class="card-header bg-white border-bottom px-4 py-3">
                        <h6 class="mb-1 fw-semibold">Preview</h6>
                        <div class="text-muted small lh-sm">Tampilan yang dilihat pendaftar</div>
                    </div>
                    <div class="card-body bg-light p-3 p-md-4">
                        <div class="bg-white rounded-3 border p-3 p-md-4">
                            <h5 class="mb-3">{{ $page->name }}</h5>
                            <div id="preview"></div>
                            <button type="button" class="btn btn-primary w-100 mt-2" disabled>Kirim Pendaftaran</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($canManage)
        <template id="row-template">
            @include('page.form._row', ['i' => '__INDEX__', 'row' => ['type' => 'text']])
        </template>
    @endif

    <script>
        (function () {
            var rows = document.getElementById('rows');
            var preview = document.getElementById('preview');
            var empty = document.getElementById('rows-empty');
            var template = document.getElementById('row-template');
            var withOptions = @json(\App\Models\PageFormField::WITH_OPTIONS);
            var inputTypes = { email: 'email', phone: 'tel', number: 'number', date: 'date', file: 'file' };
            var counter = {{ count($rows) }};
            var dirty = false;

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function refresh() {
                var html = '';
                var list = rows.querySelectorAll('[data-row]');
                empty.classList.toggle('d-none', list.length > 0);

                list.forEach(function (row, index) {
                    var type = row.querySelector('[data-type]').value;
                    var label = escapeHtml(row.querySelector('[data-label]').value || '(tanpa label)');
                    var placeholder = escapeHtml(row.querySelector('[data-placeholder]').value);
                    var help = escapeHtml(row.querySelector('[data-help]').value);
                    var required = row.querySelector('[data-required]').checked ? ' <span class="text-danger">*</span>' : '';
                    var options = row.querySelector('[data-options]').value.split('\n').map(function (o) { return o.trim(); }).filter(Boolean);
                    var hasOptions = withOptions.indexOf(type) !== -1;

                    row.querySelector('[data-row-number]').textContent = '#' + (index + 1);
                    row.querySelector('[data-options-box]').hidden = !hasOptions;

                    html += '<div class="mb-3"><div class="form-label">' + label + required + '</div>';
                    if (type === 'textarea') {
                        html += '<textarea class="form-control" rows="3" placeholder="' + placeholder + '" disabled></textarea>';
                    } else if (type === 'select') {
                        html += '<select class="form-select" disabled><option>' + (placeholder || '-- Pilih --') + '</option>'
                            + options.map(function (o) { return '<option>' + escapeHtml(o) + '</option>'; }).join('') + '</select>';
                    } else if (type === 'radio' || type === 'checkbox') {
                        html += options.map(function (o) {
                            return '<div class="form-check"><input class="form-check-input" type="' + type + '" disabled><label class="form-check-label">' + escapeHtml(o) + '</label></div>';
                        }).join('') || '<div class="text-muted small">Belum ada pilihan</div>';
                    } else {
                        html += '<input class="form-control" type="' + (inputTypes[type] || 'text') + '" placeholder="' + placeholder + '" disabled>';
                    }
                    html += (help ? '<div class="form-text">' + help + '</div>' : '') + '</div>';
                });

                preview.innerHTML = html || '<div class="text-muted small">Preview muncul setelah ada field.</div>';
            }

            if (template) {
                document.getElementById('add-row').addEventListener('click', function () {
                    var html = template.innerHTML.replace(/__INDEX__/g, 'n' + (counter++));
                    rows.insertAdjacentHTML('beforeend', html);
                    rows.lastElementChild.querySelector('[data-label]').focus();
                    dirty = true;
                    refresh();
                });

                rows.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-move], [data-remove]');
                    if (!button) return;
                    var row = button.closest('[data-row]');

                    if (button.hasAttribute('data-remove')) {
                        var label = row.querySelector('[data-label]').value;
                        if (row.querySelector('[name$="[id]"]').value && !confirm('Hapus field "' + label + '"? Terhapus setelah Simpan Semua. Jawaban lama tetap tersimpan.')) return;
                        row.remove();
                    } else if (button.dataset.move === 'up' && row.previousElementSibling) {
                        rows.insertBefore(row, row.previousElementSibling);
                    } else if (button.dataset.move === 'down' && row.nextElementSibling) {
                        rows.insertBefore(row.nextElementSibling, row);
                    }
                    dirty = true;
                    refresh();
                });

                rows.addEventListener('input', function () { dirty = true; refresh(); });
                rows.addEventListener('change', function () { dirty = true; refresh(); });

                document.getElementById('builder-form').addEventListener('submit', function (event) {
                    dirty = false;
                    event.target.querySelectorAll('[type=submit]').forEach(function (b) { b.disabled = true; });
                });
                window.addEventListener('beforeunload', function (event) {
                    if (dirty) { event.preventDefault(); event.returnValue = ''; }
                });
            }

            refresh();
        })();
    </script>
@endsection
