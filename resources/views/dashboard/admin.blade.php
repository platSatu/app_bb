@extends('layouts.dashboard')

@section('title', 'Dashboard')

@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp

@section('content')
    {{-- Jarak bawah supaya kartu terakhir tidak menempel ke footer. --}}
    <div class="pb-4">
    <div class="card">
        <div class="card-body">
            <h5 class="mb-1">{{ $greeting }}, {{ Auth::user()->name }}</h5>
            <p class="text-muted mb-0">{{ now()->translatedFormat('l, d F Y') }} · Ringkasan platform {{ config('app.name') }}.</p>
        </div>
    </div>

    @if ($cards)
        <div class="row g-3 mb-3">
            @foreach ($cards as [$label, $value, $note, $icon, $color, $url])
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ $url }}" class="card h-100 mb-0 text-body text-decoration-none">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <span class="text-muted">{{ $label }}</span>
                                <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center" style="width:36px;height:36px"><i class="{{ $icon }} fs-5"></i></span>
                            </div>
                            <h4 class="mb-1">{{ $value }}</h4>
                            <div class="small text-muted">{{ $note }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="{{ $newUsers ? 'col-xl-5' : 'col-12' }}">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-3"><i class="ri-notification-badge-line text-primary me-1"></i> Perlu Tindakan</h6>
                    @forelse ($actions as [$count, $label, $icon, $color, $url])
                        <a href="{{ $url }}" class="d-flex align-items-center gap-3 p-2 rounded-3 text-body text-decoration-none border mb-2">
                            <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px">
                                <i class="{{ $icon }} fs-5"></i>
                            </span>
                            <div class="flex-grow-1"><strong>{{ $count }}</strong> {{ $label }}</div>
                            <i class="ri-arrow-right-s-line fs-5 text-muted"></i>
                        </a>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-checkbox-circle-line fs-1 text-success d-block mb-2"></i>
                            <div class="fw-semibold text-body">Semua beres</div>
                            <div class="small">Tidak ada yang perlu ditindaklanjuti saat ini.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($newUsers)
            <div class="col-xl-7">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="ri-user-add-line text-primary me-1"></i> Pendaftar Terbaru</h6>
                            <a href="{{ route('superadmin.users.index') }}" class="small">Lihat semua</a>
                        </div>
                        @forelse ($newUsers as $item)
                            <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                <span class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-semibold flex-shrink-0" style="width:36px;height:36px">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->name, 0, 1)) }}
                                </span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $item->name }}</div>
                                    <div class="small text-muted text-truncate">{{ $item->email }}{{ $item->company ? ' · '.$item->company->name : ' · belum isi perusahaan' }}</div>
                                </div>
                                <span class="small text-muted text-nowrap">{{ $item->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4 small">Belum ada pendaftar.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($chart)
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="mb-1"><i class="ri-bar-chart-2-line text-primary me-1"></i> Pendaftar Baru 8 Minggu Terakhir</h6>
                <p class="small text-muted mb-2">Pemilik usaha yang mendaftar, per minggu (Senin–Minggu).</p>
                @include('dashboard._chart', ['id' => 'signup-chart', 'chart' => $chart, 'money' => false])
            </div>
        </div>
    @endif
    </div>
@endsection
