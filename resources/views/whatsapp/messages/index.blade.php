@extends('layouts.dashboard')

@section('title', 'Message')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Total Jadwal', $summary['total'], 'ri-calendar-schedule-line', 'primary'],
            ['Jadwal Berjalan', $summary['running'], 'ri-broadcast-line', 'warning'],
            ['Total Terkirim', $summary['sent'], 'ri-check-double-line', 'success'],
            ['Total Gagal', $summary['failed'], 'ri-error-warning-line', 'danger'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="card mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:56px;height:56px"><i class="{{ $icon }} fs-3"></i></span>
                        <div class="min-w-0">
                            <div class="text-muted text-truncate">{{ $label }}</div>
                            <div class="fs-3 fw-semibold lh-sm">{{ number_format($value, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <h4 class="mb-1">Pesan Terjadwal</h4>
                    <div class="text-muted small">Sekali kirim, berulang, atau bertahap per kontak, semua dari satu tempat.</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    <a href="{{ route('whatsapp.messages.create', ['branch' => $branch->id]) }}" class="btn btn-primary"><i class="ri-add-line"></i> Buat Pesan</a>
                </div>
            </div>

            <form method="GET" class="row g-2 mb-3" data-auto-filter>
                <input type="hidden" name="branch" value="{{ $branch->id }}">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama pesan...">
                        <button class="btn btn-light border" title="Cari"><i class="ri-search-line"></i></button>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="type" class="form-select">
                        <option value="">Semua Jenis</option>
                        @foreach (\App\Models\WaBroadcast::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        @foreach (\App\Models\WaBroadcast::STATUSES as $key => [$label])
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if (request()->hasAny(['q', 'type', 'status']))
                    <div class="col-md-2"><a href="{{ route('whatsapp.messages.index', ['branch' => $branch->id]) }}" class="btn btn-light w-100">Reset</a></div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1080px">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 1%">No</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Tujuan</th>
                            <th>Jadwal</th>
                            <th>Status Kirim</th>
                            <th class="text-center">Delivered</th>
                            <th class="text-center">Read</th>
                            <th class="text-center">Failed</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($broadcasts as $item)
                            @php
                                [$label, $color] = $item->statusMeta();
                                $stat = $stats[$item->id] ?? null;
                                $total = (int) ($stat->total ?? 0);
                            @endphp
                            <tr>
                                <td class="text-muted small">{{ $broadcasts->firstItem() + $loop->index }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    <div class="small text-muted">dari {{ $item->device?->name ?? '-' }}</div>
                                </td>
                                <td class="small text-nowrap">{{ \App\Models\WaBroadcast::TYPES[$item->type] ?? $item->type }}</td>
                                <td class="small">
                                    @forelse ($item->targetSummary() as $what => $count)
                                        <div class="text-nowrap">{{ $count }} {{ $what }}</div>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
                                <td class="small text-nowrap">
                                    {{ \App\Models\WaBroadcast::SCHEDULES[$item->schedule] ?? $item->schedule }}
                                    @if ($item->type !== 'once' && $item->starts_at)
                                        <div class="text-muted">{{ $item->starts_at->translatedFormat('d M Y') }}{{ $item->ends_at ? ' – '.$item->ends_at->translatedFormat('d M Y') : '' }}, {{ $item->starts_at->format('H:i') }}</div>
                                    @endif
                                    @if ($item->next_run_at && $item->status === 'active')
                                        <div class="text-muted">berikutnya {{ $item->next_run_at->translatedFormat('d M Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    <span class="badge bg-{{ $color }}-subtle text-{{ $color }}">{{ $label }}</span>
                                    <div class="mt-1">
                                        @if ($total)
                                            {{ (int) $stat->sent }} / {{ $total }} terkirim
                                        @else
                                            <span class="text-muted">{{ $item->last_error ? \Illuminate\Support\Str::limit($item->last_error, 40) : 'Belum dikirim' }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge bg-info-subtle text-info fs-12">{{ (int) ($stat->delivered ?? 0) }}</span></td>
                                <td class="text-center"><span class="badge bg-success-subtle text-success fs-12">{{ (int) ($stat->read ?? 0) }}</span></td>
                                <td class="text-center"><span class="badge {{ ($stat->failed ?? 0) > 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' }} fs-12">{{ (int) ($stat->failed ?? 0) }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('whatsapp.messages.show', $item) }}" class="btn btn-sm btn-light" title="Detail & riwayat"><i class="ri-history-line"></i></a>
                                    @if ($item->status === 'active')
                                        <form method="POST" action="{{ route('whatsapp.messages.pause', $item) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light" title="Jeda"><i class="ri-pause-line"></i></button></form>
                                    @elseif ($item->status === 'paused')
                                        <form method="POST" action="{{ route('whatsapp.messages.resume', $item) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light" title="Lanjutkan"><i class="ri-play-line"></i></button></form>
                                    @endif
                                    <form method="POST" action="{{ route('whatsapp.messages.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Hapus pesan beserta riwayat kirimnya?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">Belum ada pesan. Klik <strong>Buat Pesan</strong> untuk kirim sekali, berulang, atau bertahap.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $broadcasts->links() }}</div>
            <div class="small text-muted mt-2">Delivered = sudah diterima (termasuk yang sudah dibaca). Pesan ke Grup WhatsApp dihitung satu pesan.</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Pilihan Jenis/Status langsung menyaring tanpa tombol.
        document.querySelectorAll('[data-auto-filter] select').forEach(function (el) {
            el.addEventListener('change', function () { el.form.submit(); });
        });
    </script>
@endpush
