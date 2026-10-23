@extends('layouts.dashboard')

@section('title', 'Contact')
@section('page-section', 'Phone Book')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">Customers WhatsApp branch <strong>{{ $branch->name }}</strong>: kontak dari inbox, impor CSV, dan anggota grup WhatsApp. Customer Pages dan Invoice ada di menu masing-masing.</p>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#wa-import-modal"><i class="ri-upload-2-line"></i> Impor CSV</button>
                    <button type="button" class="btn btn-primary btn-sm" data-contact-new><i class="ri-add-line"></i> Tambah Kontak</button>
                </div>
            </div>

            <form method="GET" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="branch" value="{{ $branch->id }}">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, WhatsApp, atau email" value="{{ request('search') }}">
                </div>
                <div class="col-12 col-md-3">
                    <select name="group" class="form-select">
                        <option value="">Semua group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected(request('group') === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'group']))
                        <a href="{{ route('whatsapp.contacts.index', ['branch' => $branch->id]) }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Nama</th><th>WhatsApp</th><th>Email</th><th>Group</th><th>Sumber</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="fw-semibold">{{ $customer->name ?: '-' }}</td>
                                <td class="text-nowrap">{{ $customer->phone ? '+'.$customer->phone : '-' }}</td>
                                <td>{{ $customer->email ?: '-' }}</td>
                                <td>
                                    @foreach ($customer->groups as $group)
                                        <span class="badge bg-primary-subtle text-primary">{{ $group->name }}</span>
                                    @endforeach
                                </td>
                                <td class="small text-muted">{{ $customer->source ?: '-' }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary"
                                        data-contact-edit="{{ route('whatsapp.contacts.update', $customer) }}"
                                        data-contact="{{ json_encode(['name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email, 'groups' => $customer->groups->pluck('id')]) }}">
                                        <i class="ri-pencil-line"></i> Ubah
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'group']) ? 'Tidak ada kontak yang cocok.' : 'Belum ada kontak. Tambahkan manual atau impor dari CSV.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $customers->links() }}</div>
        </div>
    </div>

    {{-- Tambah / ubah kontak --}}
    <div class="modal fade" id="wa-contact-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('whatsapp.contacts.store') }}" class="modal-content" data-contact-form data-store="{{ route('whatsapp.contacts.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" data-contact-title>Tambah Kontak</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" maxlength="150" required class="form-control mb-3 @error('name') is-invalid @enderror" value="{{ old('name') }}">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="phone" maxlength="20" required inputmode="numeric" class="form-control mb-1 @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="081234567890">
                    @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <div class="form-text mb-3">Otomatis disimpan dengan awalan 62.</div>
                    <label class="form-label">Email (opsional)</label>
                    <input type="email" name="email" maxlength="255" class="form-control mb-3 @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                    @if ($groups->isNotEmpty())
                        <label class="form-label">Group</label>
                        <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto">
                            @foreach ($groups as $group)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="group_ids[]" value="{{ $group->id }}" id="g-{{ $group->id }}" @checked(in_array($group->id, (array) old('group_ids', [])))>
                                    <label class="form-check-label" for="g-{{ $group->id }}">{{ $group->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Impor CSV --}}
    <div class="modal fade" id="wa-import-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('whatsapp.contacts.import') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Impor Kontak dari CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Baris pertama berisi judul kolom: <code>nama,whatsapp,email</code>. Maksimal {{ number_format(\App\Services\Contact\ContactBookService::MAX_ROWS) }} baris, file 2 MB. Nomor yang sudah ada tidak dibuat dua kali.</p>
                    <input type="file" name="file" accept=".csv,text/csv" required class="form-control mb-3">
                    <label class="form-label">Masukkan ke group (opsional)</label>
                    <select name="group_id" class="form-select">
                        <option value="">Tanpa group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Impor</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('wa-contact-modal');
            var form = modalEl.querySelector('[data-contact-form]');
            var open = function (title, action, method, data) {
                form.action = action;
                form.elements.namedItem('_method').value = method;
                modalEl.querySelector('[data-contact-title]').textContent = title;
                var f = form.elements;
                f.namedItem('name').value = data.name || '';
                f.namedItem('phone').value = data.phone || '';
                f.namedItem('email').value = data.email || '';
                form.querySelectorAll('[name="group_ids[]"]').forEach(function (c) { c.checked = (data.groups || []).indexOf(c.value) > -1; });
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };
            document.querySelector('[data-contact-new]').addEventListener('click', function () { open('Tambah Kontak', form.dataset.store, 'POST', {}); });
            document.querySelectorAll('[data-contact-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () { open('Ubah Kontak', btn.dataset.contactEdit, 'PUT', JSON.parse(btn.dataset.contact)); });
            });
            @if ($errors->hasAny(['name', 'phone', 'email']))
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            @endif
        })();
    </script>
@endpush
