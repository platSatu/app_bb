@extends('layouts.dashboard')

@section('title', 'Dashboard')

@php
    $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
@endphp

@section('content')
    {{-- Jarak bawah supaya kartu terakhir tidak menempel ke footer. --}}
    <div class="pb-4">
    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <h5 class="mb-1">{{ $greeting }}, {{ Auth::user()->name }}</h5>
                <p class="text-muted mb-0">{{ now()->translatedFormat('l, d F Y') }} · Ini ringkasan yang perlu Anda lihat hari ini.</p>
            </div>
        </div>
    </div>

    @include('dashboard._roles')

    @if ($owner && ! $hasBranch)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="ri-store-2-line fs-5"></i>
            <div class="flex-grow-1">{{ $portal['roles']['teacher'] || $portal['roles']['student'] || $portal['roles']['payer'] ? 'Mau punya usaha sendiri juga? Buat branch pertama Anda, lalu coba paket gratis.' : 'Mulai dengan membuat branch pertama Anda. Semua aplikasi (jadwal, invoice, WhatsApp) tercatat per branch.' }}</div>
            <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
        </div>
    @elseif ($owner)
        @php
            $cards = [
                ['Pemasukan bulan ini', $rupiah($stats['income']['value']), $stats['income']['change'], 'ri-money-dollar-circle-line', 'success',
                    collect(['Tiket' => $stats['income']['parts']['ticket'], 'Katalog' => $stats['income']['parts']['catalog'], 'Invoice' => $stats['income']['parts']['invoice']])->filter()->map(fn ($v, $k) => $k.' '.$rupiah($v))->join(' · ') ?: 'Belum ada pemasukan'],
                ['Pesanan baru', number_format($stats['orders']['value'], 0, ',', '.'), $stats['orders']['change'], 'ri-shopping-cart-2-line', 'primary', 'Tiket lunas & pesanan katalog bulan ini'],
                $usesInvoice ? ['Invoice belum dibayar', number_format($stats['unpaid']['value'], 0, ',', '.'), null, 'ri-file-warning-line', 'warning', 'Total '.$rupiah($stats['unpaid']['amount'])] : null,
                ['Kontak baru', number_format($stats['contacts']['value'], 0, ',', '.'), $stats['contacts']['change'], 'ri-contacts-book-3-line', 'info', 'Masuk ke Buku Kontak bulan ini'],
            ];
            $cards = array_filter($cards);
        @endphp
        <div class="row g-3 mb-3">
            @foreach ($cards as [$label, $value, $change, $icon, $color, $note])
                <div class="col-sm-6 {{ count($cards) === 4 ? 'col-xl-3' : 'col-xl-4' }}">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <span class="text-muted">{{ $label }}</span>
                                <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center" style="width:36px;height:36px"><i class="{{ $icon }} fs-5"></i></span>
                            </div>
                            <h4 class="mb-1">{{ $value }}</h4>
                            <div class="small text-muted">
                                @if ($change !== null)
                                    <span class="{{ $change >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                        <i class="{{ $change >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>{{ abs($change) }}%
                                    </span> dari bulan lalu ·
                                @endif
                                {{ $note }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="{{ $usesSchedule ? 'col-xl-5' : 'col-12' }}">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="ri-notification-badge-line text-primary me-1"></i> Perlu Tindakan</h6>
                        @forelse ($actions as $item)
                            <a href="{{ $item['url'] }}" class="d-flex align-items-center gap-3 p-2 rounded-3 text-body text-decoration-none border mb-2">
                                <span class="rounded-circle bg-{{ $item['color'] }}-subtle text-{{ $item['color'] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px">
                                    <i class="{{ $item['icon'] }} fs-5"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <div><strong>{{ $item['count'] }}</strong> {{ $item['label'] }}</div>
                                    @if ($item['note']) <div class="small text-muted">{{ $item['note'] }}</div> @endif
                                </div>
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

            @if ($usesSchedule)
                <div class="col-xl-7">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0"><i class="ri-calendar-2-line text-primary me-1"></i> Jadwal Hari Ini</h6>
                                <a href="{{ route('schedules.sessions.index', ['date_from' => today()->toDateString(), 'date_to' => today()->toDateString()]) }}" class="small">Lihat semua</a>
                            </div>
                            @if ($today->isEmpty())
                                <div class="text-center text-muted py-4">
                                    <i class="ri-cup-line fs-1 d-block mb-2 opacity-50"></i>
                                    <div class="fw-semibold text-body">Tidak ada sesi hari ini</div>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Jam</th>
                                                <th>Pelajaran</th>
                                                <th>Pengajar</th>
                                                <th>Murid</th>
                                                <th>Ruang</th>
                                                @if ($multiBranch) <th>Branch</th> @endif
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($today as $session)
                                                <tr @class(['table-primary' => $session->status === 'scheduled' && $session->start_at->isPast() && $session->end_at->isFuture()])>
                                                    <td class="text-nowrap">
                                                        <a href="{{ route('schedules.sessions.show', $session) }}" class="fw-semibold">{{ $session->timeRange() }}</a>
                                                    </td>
                                                    <td>{{ $session->grade?->label() }}</td>
                                                    <td>{{ $session->teacher?->name }}</td>
                                                    <td class="small">{{ $session->attendances->pluck('student.name')->filter()->join(', ') ?: '-' }}</td>
                                                    <td>{{ $session->room?->name }}</td>
                                                    @if ($multiBranch) <td class="small">{{ $session->branch?->name }}</td> @endif
                                                    <td>
                                                        @if ($session->status === 'scheduled' && $session->start_at->isPast() && $session->end_at->isFuture())
                                                            <span class="badge bg-primary">Sedang berlangsung</span>
                                                        @elseif ($session->status === 'scheduled' && $session->end_at->isPast())
                                                            <span class="badge bg-danger-subtle text-danger">Belum diabsen</span>
                                                        @else
                                                            <span class="badge {{ $session->statusBadge() }}">{{ $session->statusLabel() }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="row g-3 mt-0">
            <div class="col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <h6 class="mb-1"><i class="ri-bar-chart-2-line text-primary me-1"></i> Pemasukan 8 Minggu Terakhir</h6>
                        <p class="small text-muted mb-2">Dikelompokkan per minggu (Senin–Minggu).</p>
                        @if ($chart['series'])
                            @include('dashboard._chart', ['id' => 'income-chart', 'chart' => $chart, 'money' => true])
                        @else
                            <div class="text-center text-muted py-5">
                                <i class="ri-bar-chart-2-line fs-1 d-block mb-2 opacity-50"></i>
                                <div class="small">Grafik muncul setelah ada pemasukan dari tiket, katalog, atau invoice.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-body d-flex flex-column">
                        <h6 class="mb-3"><i class="ri-wallet-3-line text-primary me-1"></i> Saldo Anda</h6>
                        <h3 class="mb-1">{{ $rupiah($balance) }}</h3>
                        <p class="small text-muted">Hasil penjualan tiket masuk ke saldo ini dan bisa ditarik ke rekening.</p>
                        <div class="d-flex flex-wrap gap-2 mt-auto">
                            <a href="{{ route('account.deposits.create') }}" class="btn btn-sm btn-primary"><i class="ri-add-line"></i> Tambah Saldo</a>
                            <a href="{{ route('profile.withdrawals') }}" class="btn btn-sm btn-outline-secondary">Tarik Saldo</a>
                            <a href="{{ route('profile.history') }}" class="btn btn-sm btn-light">Riwayat</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-12">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="ri-history-line text-primary me-1"></i> Aktivitas Terbaru</h6>
                        @forelse ($recent as $row)
                            <a href="{{ $row['url'] }}" class="d-flex align-items-center gap-3 py-2 text-body text-decoration-none {{ $loop->last ? '' : 'border-bottom' }}">
                                <span class="rounded-circle bg-{{ $row['color'] }}-subtle text-{{ $row['color'] }} d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px"><i class="{{ $row['icon'] }}"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="text-truncate">{{ $row['title'] }}</div>
                                    <div class="small text-muted">{{ $row['at']->diffForHumans() }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold text-nowrap">{{ $row['amount'] }}</div>
                                    @isset($row['badge']) <span class="badge {{ $row['badge'][1] }}">{{ $row['badge'][0] }}</span> @endisset
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="ri-inbox-line fs-1 d-block mb-2 opacity-50"></i>
                                <div class="small">Belum ada transaksi. Pembelian tiket, pesanan katalog, dan pembayaran invoice akan muncul di sini.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
    </div>
@endsection
