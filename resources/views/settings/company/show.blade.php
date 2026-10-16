@extends('layouts.dashboard')

@section('title', 'Settings')

@section('content')
    @include('settings._tabs', ['active' => 'company'])

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Perusahaan</th>
                            <th>Email</th>
                            <th>Telepon / Fax</th>
                            <th>WhatsApp</th>
                            <th class="text-center">Branch</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="h-36px w-36px rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0">
                                        @if ($company->logoUrl())
                                            <img src="{{ $company->logoUrl() }}" alt="" class="w-100 h-100" style="object-fit: contain;">
                                        @else
                                            <i class="ri-building-line text-muted"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $company->name }}</div>
                                        <div class="text-muted fs-12">{{ $company->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $company->email ?: '-' }}</td>
                            <td class="text-nowrap">
                                <div>{{ $company->phone ?: '-' }}</div>
                                @if ($company->fax)
                                    <div class="text-muted small">Fax {{ $company->fax }}</div>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if ($company->handphone)
                                    <i class="ri-whatsapp-line text-success"></i> +{{ $company->handphone }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('settings.branches.index') }}">{{ $company->branches_count }}</a>
                            </td>
                            <td>
                                <span class="badge {{ $company->status === \App\Models\UserCompany::STATUS_INACTIVE ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                    {{ $company->status === \App\Models\UserCompany::STATUS_INACTIVE ? 'Tidak Aktif' : 'Aktif' }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('settings.branches.create') }}" class="btn btn-sm rounded-pill btn-outline-primary">
                                    <i class="ri-add-line"></i> Add Branch
                                </a>
                                <a href="{{ route('settings.company.edit') }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                    <i class="ri-edit-line"></i> Edit
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            @if ($company->description)
                <div class="mt-3">
                    <div class="text-muted small mb-1">Deskripsi</div>
                    <p class="mb-0" style="white-space: pre-line;">{{ $company->description }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
