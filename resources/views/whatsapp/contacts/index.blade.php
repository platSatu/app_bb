@extends('layouts.dashboard')

@section('title', 'Customers')
@section('page-section', 'Phone Book')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">Customers WhatsApp branch <strong>{{ $branch->name }}</strong>: kontak dari inbox, impor CSV, dan anggota grup WhatsApp. Customer Pages dan Invoice ada di menu masing-masing.</p>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#customer-import-modal"><i class="ri-file-excel-2-line"></i> Impor Excel</button>
                    <button type="button" class="btn btn-primary btn-sm" data-customer-new><i class="ri-add-line"></i> Tambah Kontak</button>
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
                                <td class="text-end text-nowrap">
                                    @include('contact.customer._actions', [
                                        'routes' => 'whatsapp.contacts',
                                        'hasAccount' => $customer->email && isset($accounts[strtolower($customer->email)]),
                                        'editData' => ['name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email, 'groups' => $customer->groups->pluck('id')],
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ request()->hasAny(['search', 'group']) ? 'Tidak ada kontak yang cocok.' : 'Belum ada kontak. Tambahkan manual atau impor dari Excel.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $customers->links() }}</div>
        </div>
    </div>

    {{-- Tambah / ubah kontak --}}
    <div class="modal fade" id="customer-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('whatsapp.contacts.store') }}" class="modal-content" data-customer-form data-store="{{ route('whatsapp.contacts.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" data-customer-title>Tambah Kontak</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('contact.customer._fields')
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

    @include('contact.customer._import-modal', ['action' => route('whatsapp.contacts.import'), 'template' => route('whatsapp.contacts.template'), 'branches' => collect([$branch]), 'groups' => $groups])
    @include('contact.customer._modal-script')
@endsection
