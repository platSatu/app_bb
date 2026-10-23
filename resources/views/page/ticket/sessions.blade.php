@extends('layouts.dashboard')

@section('title', 'Sesi dan Absensi')
@section('page-section', $routes === 'pages' ? 'Pages' : 'Superadmin')

@push('styles')
    <style>
        .session-card { border: 1px solid #edf0f3; border-radius: 1rem; transition: box-shadow .15s; }
        .session-card:hover { box-shadow: 0 8px 24px -12px rgba(16,24,40,.18); }
        .session-date { width: 64px; flex-shrink: 0; text-align: center; border-radius: .875rem; background: rgba(var(--bs-primary-rgb), .08); color: var(--bs-primary); padding: .5rem 0; line-height: 1.1; }
        .session-date b { display: block; font-size: 1.5rem; }
        .session-date small { text-transform: uppercase; font-size: .7rem; letter-spacing: .08em; }
        .chip { display: inline-flex; align-items: center; gap: .25rem; background: #f3f5f7; border-radius: 99px; padding: .15rem .6rem; font-size: .78rem; color: #4b5563; }
    </style>
@endpush

@section('content')
    @include('page.category._tabs', ['active' => 'ticket-sessions'])

    @php
        $newOpen = old('_form') === 'session-new' || $sessions->isEmpty();
    @endphp

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fw-semibold">Jadwal Sesi & Kelas</h6>
                        <div class="text-muted small lh-sm">Setiap sesi punya absensinya sendiri. Peserta absen dengan QR di ID card, hanya di jam yang Anda tentukan.</div>
                    </div>
                    @if ($canManage)
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#new-session" aria-expanded="{{ $newOpen ? 'true' : 'false' }}"><i class="ri-add-line me-1"></i>Tambah Sesi</button>
                    @endif
                </div>
                <div class="card-body p-3 p-md-4">
                    @if ($canManage)
                        <div class="collapse {{ $newOpen ? 'show' : '' }}" id="new-session">
                            <div class="border rounded-4 p-3 p-md-4 mb-4 bg-light-subtle">
                                <h6 class="fw-semibold mb-3">Sesi baru</h6>
                                @include('page.ticket._session-form', ['session' => null, 'bag' => 'session-new', 'action' => route($routes.'.ticket.sessions.store', $page), 'method' => 'POST'])
                            </div>
                        </div>
                    @endif

                    @forelse ($sessions as $session)
                        @php
                            $stat = $stats[$session->getKey()];
                            $percent = $stat['eligible'] ? round($stat['attended'] / $stat['eligible'] * 100) : 0;
                            $editOpen = old('_form') === 'session-'.$session->getKey();
                        @endphp
                        <div class="session-card p-3 mb-3">
                            <div class="d-flex gap-3">
                                <div class="session-date"><small>{{ $session->starts_at->translatedFormat('M') }}</small><b>{{ $session->starts_at->format('d') }}</b><small>{{ $session->starts_at->translatedFormat('D') }}</small></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between gap-2 flex-wrap">
                                        <div class="min-w-0">
                                            <div class="fw-semibold fs-6">{{ $session->name }}</div>
                                            <div class="small text-muted">{{ $session->starts_at->format('H:i') }} – {{ $session->ends_at->format('H:i') }}{{ $session->room ? ' · '.$session->room : '' }}</div>
                                        </div>
                                        <span class="badge {{ $session->stateBadge() }} align-self-start">{{ $session->stateLabel() }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <span class="chip"><i class="ri-door-open-line"></i>Scan {{ $session->opensAt()->format('H:i') }} – {{ $session->closesAt()->format('H:i') }}</span>
                                        @if (empty($session->category_ids))
                                            <span class="chip"><i class="ri-group-line"></i>Semua kategori</span>
                                        @else
                                            @foreach ($categories->whereIn('id', $session->category_ids) as $category)
                                                <span class="chip"><i class="ri-coupon-3-line"></i>{{ $category->name }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mt-3">
                                        <div class="progress flex-grow-1" style="height: 8px;"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>
                                        <span class="small text-nowrap"><b>{{ $stat['attended'] }}</b> / {{ $stat['eligible'] }} hadir</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a href="{{ route($routes.'.ticket.sessions.show', [$page, $session]) }}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="ri-list-check-2 me-1"></i>Daftar hadir</a>
                                        @if ($canManage)
                                            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#edit-{{ $session->getKey() }}"><i class="ri-edit-line me-1"></i>Ubah</button>
                                            <form method="POST" action="{{ route($routes.'.ticket.sessions.destroy', [$page, $session]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus sesi '.$session->name.'?') }});">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if ($canManage)
                                <div class="collapse {{ $editOpen ? 'show' : '' }}" id="edit-{{ $session->getKey() }}">
                                    <div class="border-top mt-3 pt-3">
                                        @include('page.ticket._session-form', ['session' => $session, 'bag' => 'session-'.$session->getKey(), 'action' => route($routes.'.ticket.sessions.update', [$page, $session]), 'method' => 'PUT'])
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-calendar-event-line fs-1 d-block mb-2"></i>
                            Belum ada sesi. Tambahkan kelas pertama Anda di atas ya.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @include('page.ticket._scanner-links', [
                'links' => $scanners,
                'type' => \App\Models\TicketScanner::TYPE_ATTENDANCE,
                'icon' => 'ri-calendar-check-line',
                'title' => 'Link Petugas Absensi',
                'description' => 'Buat satu link per kelas atau petugas. Petugas memilih sesinya sendiri di halaman scan.',
                'placeholder' => 'mis. Petugas Ruang Melati',
                'emptyText' => 'Belum ada link petugas absensi.',
            ])

            <div class="card">
                <div class="card-header bg-white border-bottom px-4 py-3"><h6 class="mb-0 fw-semibold">Aturan absensi</h6></div>
                <div class="card-body p-3 p-md-4 small">
                    <div class="d-flex gap-2 mb-2"><i class="ri-id-card-line text-primary"></i><div>Hanya QR di <b>ID card</b> yang diterima. QR e-ticket akan ditolak dengan pesan yang jelas.</div></div>
                    <div class="d-flex gap-2 mb-2"><i class="ri-time-line text-primary"></i><div>Di luar jam scan, kartu otomatis ditolak. Waktu yang dipakai adalah jam server, bukan jam HP petugas.</div></div>
                    <div class="d-flex gap-2 mb-2"><i class="ri-repeat-one-line text-primary"></i><div>Satu peserta hanya tercatat <b>sekali</b> per sesi, walau discan berkali-kali atau oleh dua petugas bersamaan.</div></div>
                    <div class="d-flex gap-2"><i class="ri-shield-keyhole-line text-primary"></i><div>Kartu yang dicetak ulang dengan QR baru membuat kartu lamanya tidak berlaku lagi.</div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
