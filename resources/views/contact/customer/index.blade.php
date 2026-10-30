@extends('layouts.dashboard')

@php
    $isAdmin = $base === 'superadmin.contacts';
    $appLabel = $app ? \App\Models\Customer::APPS[$app] : null;
    // Hapus banyak: hanya superadmin/staff dengan izin kelola.
    $canBulk = $isAdmin && auth()->user()->canAccess('superadmin.customers', \App\Support\Menu::ABILITY_MANAGE) && $customers->isNotEmpty();
@endphp

@section('title', $isAdmin ? 'Customer' : 'Customers')
@section('page-section', $isAdmin ? 'Superadmin' : $appLabel)

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($isAdmin)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted small">Semua customer milik owner di semua aplikasi. Export mengikuti filter yang sedang dipakai.</div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('superadmin.contacts.export', request()->only(['search', 'app'])) }}" class="btn btn-outline-success btn-sm"><i class="ri-file-excel-2-line"></i> Export Excel</a>
                        @if ($writable->isNotEmpty())
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#customer-import-modal"><i class="ri-upload-2-line"></i> Impor Excel</button>
                        @endif
                    </div>
                </div>
            @else
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted small">
                        @if ($app === 'events')
                            Orang yang membeli tiket di event Anda. Masuk otomatis, bisa juga ditambah manual.
                        @elseif ($app === 'pages')
                            Orang yang memesan katalog atau mengikuti kuis di page Anda. Masuk otomatis, bisa juga ditambah manual.
                        @else
                            Orang yang Anda tagih lewat Invoice. Bisa ditambah di sini atau dari menu Kategori &rsaquo; Customer.
                        @endif
                        Customer aplikasi lain tidak ikut tampil di sini.
                    </div>
                    @if ($writable->isNotEmpty())
                        <div class="d-flex gap-2">
                            @if ($registerLinks !== null)
                                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#register-link-modal"><i class="ri-link"></i> Link Pendaftaran</button>
                            @endif
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#customer-import-modal"><i class="ri-file-excel-2-line"></i> Impor Excel</button>
                            <button type="button" class="btn btn-primary btn-sm" data-customer-new><i class="ri-add-line"></i> Tambah Customer</button>
                        </div>
                    @endif
                </div>
            @endif

            <form method="GET" class="row g-2 align-items-center mb-3">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, WhatsApp, atau email" value="{{ request('search') }}">
                </div>
                @if ($isAdmin)
                    <div class="col-12 col-md-3">
                        <select name="app" class="form-select">
                            <option value="">Semua aplikasi</option>
                            @foreach (\App\Models\Customer::APPS as $key => $label)
                                <option value="{{ $key }}" @selected(request('app') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($branches->count() > 1)
                    <div class="col-12 col-md-3">
                        <select name="branch" class="form-select">
                            <option value="">Semua branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch') === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-grow-1"><i class="ri-search-line"></i> Cari</button>
                    @if (request()->hasAny(['search', 'branch', 'app']))
                        <a href="{{ url()->current() }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @if ($canBulk)
                <form id="bulk-delete-form" method="POST" action="{{ route('superadmin.contacts.destroy-many', request()->only(['search', 'app'])) }}"
                    class="alert alert-danger d-none align-items-center flex-wrap gap-2 py-2 mb-2" data-bulk-bar data-total="{{ $customers->total() }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="all" value="0" data-bulk-all>
                    <span class="small"><b data-bulk-count>0</b> customer dipilih.</span>
                    @if ($customers->total() > $customers->count())
                        <button type="button" class="btn btn-link btn-sm p-0 d-none" data-bulk-select-all>Pilih semua {{ number_format($customers->total(), 0, ',', '.') }} customer yang cocok dengan filter</button>
                    @endif
                    <button type="submit" class="btn btn-danger btn-sm ms-auto"><i class="ri-delete-bin-line"></i> Hapus yang dipilih</button>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            @if ($canBulk)
                                <th style="width: 32px"><input type="checkbox" class="form-check-input" data-bulk-page title="Pilih semua di halaman ini"></th>
                            @endif
                            <th>Nama</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>{{ $isAdmin ? 'Perusahaan / Branch' : 'Branch' }}</th>
                            <th class="text-center">{{ $isAdmin ? 'Aplikasi' : (in_array($app, ['events', 'pages'], true) ? 'Transaksi' : 'Invoice') }}</th>
                            @if ($isAdmin)
                                <th>Sumber</th>
                                <th class="text-center">Akun</th>
                                <th>Pertama</th>
                            @endif
                            <th>Terakhir</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                @if ($canBulk)
                                    <td><input type="checkbox" class="form-check-input" name="ids[]" value="{{ $customer->id }}" form="bulk-delete-form" data-bulk-row></td>
                                @endif
                                <td class="fw-semibold">{{ $customer->name ?: '-' }}</td>
                                <td class="text-nowrap">{{ $customer->phone ? '+'.$customer->phone : '-' }}</td>
                                <td>{{ $customer->email ?: '-' }}</td>
                                <td>
                                    @if ($isAdmin)
                                        <div>{{ $customer->company?->name ?? '-' }}</div>
                                        <div class="text-muted small">{{ $customer->branch?->name }}</div>
                                    @else
                                        {{ $customer->branch?->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($isAdmin)
                                        <span class="badge bg-light text-dark border">{{ $customer->appLabel() }}</span>
                                    @else
                                        {{ in_array($app, ['events', 'pages'], true) ? $customer->submissions_count : $customer->invoices_count }}
                                    @endif
                                </td>
                                @if ($isAdmin)
                                    <td class="small">{{ ucfirst((string) $customer->source) ?: '-' }}</td>
                                    <td class="text-center">
                                        @if ($customer->email && isset($accounts[strtolower($customer->email)]))
                                            <span class="badge bg-success-subtle text-success">Ya</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap small">{{ $customer->first_seen_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                @endif
                                <td class="text-nowrap small">{{ $customer->last_seen_at?->translatedFormat('d M Y H:i') ?? '-' }}</td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                                        <a href="{{ route($base.'.show', $customer) }}" class="btn btn-sm rounded-pill btn-outline-secondary" title="Detail"><i class="ri-eye-line"></i></a>
                                        @unless ($isAdmin)
                                            @include('contact.customer._actions', [
                                                'routes' => $base,
                                                'hasAccount' => $customer->email && isset($accounts[strtolower($customer->email)]),
                                                'editData' => ['name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email],
                                            ])
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 10 : 7 }}" class="text-center text-muted py-4">
                                    @if (request()->hasAny(['search', 'branch', 'app']))
                                        Tidak ada customer yang cocok.
                                    @elseif ($app === 'events')
                                        Belum ada customer. Customer masuk otomatis saat ada yang membeli tiket di event Anda.
                                    @elseif ($app === 'pages')
                                        Belum ada customer. Customer masuk otomatis saat ada yang memesan atau mendaftar di page Anda.
                                    @elseif ($app === 'invoices')
                                        Belum ada customer. Tambahkan dari menu Invoice &rsaquo; Kategori &rsaquo; Customer.
                                    @else
                                        Belum ada customer.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $customers->links() }}</div>
        </div>
    </div>

    @if (! $isAdmin && $writable->isNotEmpty())
        {{-- Tambah / ubah customer --}}
        <div class="modal fade" id="customer-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route($base.'.store') }}" class="modal-content" data-customer-form data-store="{{ route($base.'.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" data-customer-title>Tambah Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div data-branch-field>@include('contact.customer._branch-field', ['branches' => $writable])</div>
                        @include('contact.customer._fields')
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        @include('contact.customer._import-modal', ['action' => route($base.'.import'), 'template' => route($base.'.template'), 'branches' => $writable])
        @include('contact.customer._modal-script')
        @if ($registerLinks !== null)
            @include('contact.customer._register-link-modal', ['branches' => $writable, 'links' => $registerLinks])
        @endif
    @endif

    @if ($canBulk)
        <script>
            (function () {
                var bar = document.querySelector('[data-bulk-bar]'), page = document.querySelector('[data-bulk-page]');
                var rows = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-row]'));
                var all = bar.querySelector('[data-bulk-all]'), selectAll = bar.querySelector('[data-bulk-select-all]');
                var total = Number(bar.dataset.total);
                var number = function (n) { return n.toLocaleString('id-ID'); };

                var refresh = function () {
                    var checked = rows.filter(function (r) { return r.checked; }).length;
                    if (all.value !== '1') bar.querySelector('[data-bulk-count]').textContent = number(checked);
                    bar.classList.toggle('d-none', checked === 0);
                    bar.classList.toggle('d-flex', checked > 0);
                    page.checked = checked === rows.length;
                    page.indeterminate = checked > 0 && checked < rows.length;
                    if (selectAll) selectAll.classList.toggle('d-none', checked !== rows.length || all.value === '1');
                };
                var reset = function () { all.value = '0'; };

                page.addEventListener('change', function () { reset(); rows.forEach(function (r) { r.checked = page.checked; }); refresh(); });
                rows.forEach(function (r) { r.addEventListener('change', function () { reset(); refresh(); }); });
                if (selectAll) selectAll.addEventListener('click', function () {
                    all.value = '1';
                    bar.querySelector('[data-bulk-count]').textContent = number(total);
                    selectAll.classList.add('d-none');
                });
                bar.addEventListener('submit', function (event) {
                    var count = all.value === '1' ? total : rows.filter(function (r) { return r.checked; }).length;
                    if (!confirm('Hapus ' + number(count) + ' customer? Riwayat transaksi/invoice tetap tersimpan, tapi data customer ini hilang.')) {
                        event.preventDefault();
                        return;
                    }
                    bar.querySelector('[type=submit]').disabled = true;
                });
            })();
        </script>
    @endif

    @if ($isAdmin && $writable->isNotEmpty())
        @include('contact.customer._import-modal', ['action' => route('superadmin.contacts.import'), 'template' => route('superadmin.contacts.template'), 'branches' => $writable, 'apps' => \Illuminate\Support\Arr::except(\App\Models\Customer::APPS, \App\Models\Customer::APP_WEBSITE)])
    @endif
@endsection
