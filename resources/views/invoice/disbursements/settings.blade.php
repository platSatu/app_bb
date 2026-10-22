@extends('layouts.dashboard')

@section('title', 'Pengaturan Disbursement')
@section('page-section', 'Invoice')

@php($rupiah = fn ($v) => 'Rp '.number_format($v, 0, ',', '.'))

@section('content')
    @include('invoice.disbursements._tabs')

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <h6 class="mb-3">Disbursement lewat API</h6>
                    @unless ($client)
                        <p class="text-muted small">Website Anda memakai API key yang sama dengan Invoice. Buat API key dulu di menu API & Integrasi.</p>
                        <a href="{{ route('invoices.api.index', ['branch' => $branch->id]) }}" class="btn btn-primary btn-sm"><i class="ri-key-2-line me-1"></i>Buka API & Integrasi</a>
                    @else
                        <form method="POST" action="{{ route('invoices.disbursements.settings.update', $client) }}">
                            @csrf
                            @method('PUT')
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="payout_enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="payout_enabled" name="payout_enabled" value="1" @checked($client->payout_enabled)>
                                <label class="form-check-label" for="payout_enabled">Izinkan website mengirim dana lewat API</label>
                            </div>
                            <label class="form-label small mb-1">Batas per transaksi (Rp)</label>
                            <input type="number" name="payout_max_amount" min="10000" step="1" class="form-control mb-1 @error('payout_max_amount') is-invalid @enderror" value="{{ old('payout_max_amount', $client->payout_max_amount) }}" placeholder="Kosong = tanpa batas">
                            @error('payout_max_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <label class="form-label small mb-1 mt-2">Batas per hari (Rp)</label>
                            <input type="number" name="payout_daily_limit" min="10000" step="1" class="form-control mb-1 @error('payout_daily_limit') is-invalid @enderror" value="{{ old('payout_daily_limit', $client->payout_daily_limit) }}" placeholder="Kosong = tanpa batas">
                            @error('payout_daily_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text mb-3">Disarankan diisi. Kalau API key bocor, batas ini yang membatasi jumlah dana yang bisa keluar.</div>
                            <button class="btn btn-primary btn-sm">Simpan</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-0 h-100">
                <div class="card-body">
                    <h6 class="mb-3">Biaya & Aturan</h6>
                    <ul class="small mb-3 ps-3">
                        <li>Biaya per transfer <strong>{{ $rupiah($setting->withdrawal_fee) }}</strong>, dipotong dari jumlah yang dikirim.</li>
                        <li>Minimal kirim <strong>{{ $rupiah($setting->min_amount) }}</strong>; yang diterima minimal Rp 10.000.</li>
                        <li>Dana hanya bisa dikirim ke rekening penerima yang aktif, dan nama di bank harus sama.</li>
                        <li>Saldo langsung ditahan saat dikirim. Kalau transfer gagal, saldo otomatis kembali.</li>
                    </ul>
                    <h6 class="mb-2">Endpoint API</h6>
                    <div class="small font-monospace">
                        <div>GET&nbsp; {{ url('/api/v1/banks') }}</div>
                        <div>POST {{ url('/api/v1/payout-accounts') }}</div>
                        <div>POST {{ url('/api/v1/payouts') }}</div>
                        <div>GET&nbsp; {{ url('/api/v1/payouts/{id}') }}</div>
                    </div>
                    <a href="{{ route('invoices.docs') }}" class="d-inline-block small mt-3"><i class="ri-book-open-line"></i> Dokumentasi lengkap</a>
                </div>
            </div>
        </div>
    </div>
@endsection
