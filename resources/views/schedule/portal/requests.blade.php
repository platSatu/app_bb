@extends('layouts.dashboard')

@section('title', 'Portal Murid & Pengajar')
@section('page-section', 'Schedules')

@use('App\Models\SchedulePortalLink')
@use('App\Models\SchedulePortalRequest')

@php
    $isPending = $status === SchedulePortalRequest::STATUS_PENDING;
    $showBranch = $branches->count() > 1;
    $filtered = request()->hasAny(['search', 'branch', 'type']);
@endphp

@section('content')
    @include('schedule.portal._tabs', ['pending' => $counts[SchedulePortalRequest::STATUS_PENDING] ?? 0])

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills gap-2 mb-3">
                @foreach (SchedulePortalRequest::STATUS_LABELS as $value => $label)
                    <li class="nav-item">
                        <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}" class="nav-link py-1 px-3 rounded-pill {{ $status === $value ? 'active' : 'bg-light text-body' }}">
                            {{ $label }} <span class="ms-1 opacity-75">{{ $counts[$value] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <form method="GET" class="row g-2 align-items-center mb-3">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="col-12 col-md">
                    <input type="text" name="search" class="form-control" maxlength="100" placeholder="Cari nama, email, atau nomor WA" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select">
                        <option value="">Murid & pengajar</option>
                        @foreach (SchedulePortalLink::TYPE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($showBranch)
                    <div class="col-6 col-md-2">
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
                    @if ($filtered)
                        <a href="{{ route('schedules.portal.requests', ['status' => $status]) }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            @if ($isPending)
                <p class="small text-muted mb-3">
                    <i class="ri-information-line"></i> Kalau orangnya sudah ada di daftar Murid/Pengajar, pilih namanya supaya riwayat jadwalnya ikut tersambung. Kalau belum ada, pilih <strong>Buat data baru</strong>.
                </p>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Daftar Sebagai</th>
                            <th>Kontak</th>
                            @if ($showBranch) <th>Branch</th> @endif
                            <th>{{ $isPending ? 'Tanggal Daftar' : 'Diproses' }}</th>
                            <th class="text-end" style="min-width: {{ $isPending ? '340px' : '0' }}">{{ $isPending ? 'Hubungkan & Setujui' : 'Keterangan' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    @if ($item->guardian_name)
                                        <div class="text-muted small">Didaftarkan oleh {{ $item->guardian_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $item->isStudent() ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }}">{{ $item->typeLabel() }}</span>
                                </td>
                                <td class="small">
                                    @if ($item->phone) <div><i class="ri-whatsapp-line text-success"></i> {{ $item->phone }}</div> @endif
                                    @if ($item->email) <div class="text-muted">{{ $item->email }}</div> @endif
                                </td>
                                @if ($showBranch) <td>{{ $item->branch?->name }}</td> @endif
                                <td class="small text-nowrap">{{ ($isPending ? $item->created_at : $item->reviewed_at)?->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($isPending)
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="POST" action="{{ route('schedules.portal.approve', $item) }}" class="d-flex gap-2">
                                                @csrf
                                                <select name="member_id" class="form-select form-select-sm" style="max-width: 220px" required>
                                                    @foreach ($item->matches as $match)
                                                        <option value="{{ $match->id }}">{{ $match->name }}{{ $match->phone ? ' · '.$match->phone : '' }}</option>
                                                    @endforeach
                                                    <option value="new" @selected($item->matches->isEmpty())>+ Buat data baru</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap"><i class="ri-check-line"></i> Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('schedules.portal.reject', $item) }}"
                                                onsubmit="var r = prompt('Alasan ditolak (boleh dikosongkan):'); if (r === null) return false; this.reason.value = r;">
                                                @csrf
                                                <input type="hidden" name="reason">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Tolak"><i class="ri-close-line"></i></button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span>
                                        @if ($item->reject_reason)
                                            <div class="small text-muted mt-1">{{ $item->reject_reason }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + ($showBranch ? 1 : 0) }}" class="text-center text-muted py-5">
                                    @if ($isPending && ! $filtered)
                                        <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                                        Belum ada pendaftar baru. Bagikan link di tab <a href="{{ route('schedules.portal.links') }}">Link Pendaftaran</a> ke murid, orang tua, atau pengajar.
                                    @else
                                        Tidak ada data yang cocok.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
