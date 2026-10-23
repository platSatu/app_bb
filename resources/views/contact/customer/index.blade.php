@extends('layouts.dashboard')

@php
    $isAdmin = $base === 'superadmin.contacts';
    $appLabel = $app ? \App\Models\Customer::APPS[$app] : null;
@endphp

@section('title', $isAdmin ? 'Buku Kontak' : 'Customers')
@section('page-section', $isAdmin ? 'Superadmin' : $appLabel)

@section('content')
    <div class="card">
        <div class="card-body">
            @unless ($isAdmin)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted small">
                        @if ($app === 'pages')
                            Orang yang membeli tiket, memesan katalog, atau mengikuti kuis di page Anda. Masuk otomatis, bisa juga ditambah manual.
                        @else
                            Orang yang Anda tagih lewat Invoice. Bisa ditambah di sini atau dari menu Kategori &rsaquo; Customer.
                        @endif
                        Customer aplikasi lain tidak ikut tampil di sini.
                    </div>
                    @if ($writable->isNotEmpty())
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#customer-import-modal"><i class="ri-file-excel-2-line"></i> Impor Excel</button>
                            <button type="button" class="btn btn-primary btn-sm" data-customer-new><i class="ri-add-line"></i> Tambah Customer</button>
                        </div>
                    @endif
                </div>
            @endunless

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

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>WhatsApp</th>
                            <th>Email</th>
                            <th>{{ $isAdmin ? 'Perusahaan / Branch' : 'Branch' }}</th>
                            <th class="text-center">{{ $isAdmin ? 'Aplikasi' : ($app === 'pages' ? 'Transaksi' : 'Invoice') }}</th>
                            <th>Terakhir</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
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
                                        {{ $app === 'pages' ? $customer->submissions_count : $customer->invoices_count }}
                                    @endif
                                </td>
                                <td class="text-nowrap small">{{ $customer->last_seen_at->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1 align-items-center">
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
                                <td colspan="7" class="text-center text-muted py-4">
                                    @if (request()->hasAny(['search', 'branch', 'app']))
                                        Tidak ada customer yang cocok.
                                    @elseif ($app === 'pages')
                                        Belum ada customer. Customer masuk otomatis saat ada yang membeli atau mendaftar di page Anda.
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
    @endif
@endsection
