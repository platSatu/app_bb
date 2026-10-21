@extends('layouts.dashboard')

@section('title', 'My Account')

@use('App\Support\Apps')

@section('content')
    @include('profile._tabs', ['active' => 'packages'])

    <div class="pb-4">
        {{-- Layanan aktif per branch --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h6 class="mb-0 flex-grow-1"><i class="ri-shield-check-line text-primary me-1"></i> Layanan Aktif per Branch</h6>
                    <a href="{{ route('packages.catalog') }}" class="btn btn-sm btn-primary"><i class="ri-vip-crown-line"></i> Beli Paket</a>
                    <a href="{{ route('packages.redeem') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-coupon-2-line"></i> Redeem Voucher</a>
                </div>
                <p class="small text-muted mb-3">Layanan yang habis otomatis terkunci. Membeli lagi sebelum habis akan menambah masa aktif dari tanggal berakhir yang sekarang.</p>

                @if ($branches->isEmpty())
                    <div class="alert alert-info mb-0 d-flex align-items-center gap-2">
                        <i class="ri-store-2-line fs-5"></i>
                        <div class="flex-grow-1">Buat branch dulu ya, paket diaktifkan per branch.</div>
                        <a href="{{ route('settings.branches.index') }}" class="btn btn-sm btn-primary">Buat Branch</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Branch</th>
                                    @foreach (Apps::LIST as $app)
                                        <th class="text-center"><i class="{{ $app['icon'] }} me-1"></i>{{ $app['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($branches as $branch)
                                    @php($own = ($subscriptions[$branch->id] ?? collect())->keyBy('app_key'))
                                    <tr>
                                        <td class="fw-semibold">{{ $branch->name }}</td>
                                        @foreach (Apps::LIST as $key => $app)
                                            @php($sub = $own->get($key))
                                            <td class="text-center">
                                                @if ($sub && $sub->isActive())
                                                    <span class="badge {{ $sub->daysLeft() <= 7 ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}">Aktif · {{ $sub->daysLeft() }} hari lagi</span>
                                                    <div class="small text-muted mt-1">s/d {{ $sub->ends_at->translatedFormat('d M Y') }}</div>
                                                @elseif ($sub)
                                                    <span class="badge bg-danger-subtle text-danger">Habis</span>
                                                    <div class="small text-muted mt-1">{{ $sub->ends_at->translatedFormat('d M Y') }}</div>
                                                @else
                                                    <span class="badge bg-light text-muted">Belum aktif</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if ($branches->isNotEmpty())
            <div class="row g-3">
                {{-- Riwayat --}}
                <div class="col-12">
                    <div class="card h-100 mb-0">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="ri-history-line text-primary me-1"></i> Riwayat Paket</h6>
                            @forelse ($histories as $history)
                                <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                                    <div class="flex-grow-1">
                                        <div><strong>{{ $history->appLabel() }}</strong> di {{ $history->branch?->name }} {{ $history->days >= 0 ? '+'.$history->days : $history->days }} hari</div>
                                        <div class="small text-muted">{{ $history->sourceLabel() }}{{ $history->package ? ' · '.$history->package->name : '' }} · aktif s/d {{ $history->new_ends_at->translatedFormat('d M Y') }}</div>
                                    </div>
                                    <span class="small text-muted text-nowrap">{{ $history->created_at->translatedFormat('d M Y') }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted small py-4">Belum ada riwayat paket.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
