@extends('layouts.dashboard')

@section('title', 'My Account')

@section('content')
    @include('profile._tabs', ['active' => 'referral'])

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-1">Kode Referral Anda</h5>
                    <p class="text-muted mb-4">Bagikan link ini. Siapa pun yang daftar lewat link Anda akan tercatat sebagai ajakan Anda.</p>

                    <div class="text-center border rounded-3 py-4 mb-3 bg-light">
                        <div class="text-muted small mb-1">Kode</div>
                        <div class="fs-3 fw-bold text-primary letter-spacing-2">{{ $referralCode->code }}</div>
                    </div>

                    <label for="referral-link" class="form-label">Link Referral</label>
                    <div class="input-group" data-referral-share data-link="{{ $referralCode->link() }}">
                        <input type="text" id="referral-link" class="form-control" value="{{ $referralCode->link() }}" readonly>
                        <button type="button" class="btn btn-outline-primary" data-copy title="Salin link">
                            <i class="ri-file-copy-line"></i> Salin
                        </button>
                        <button type="button" class="btn btn-outline-primary d-none" data-share title="Bagikan link">
                            <i class="ri-share-forward-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">Orang yang Anda Ajak</h5>
                        <span class="badge bg-primary-subtle text-primary">{{ $referrals->total() }} orang</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($referrals as $referral)
                                    <tr>
                                        <td>{{ $referral->referredUser?->name ?? '-' }}</td>
                                        <td>{{ $referral->created_at->translatedFormat('d F Y') }}</td>
                                        <td>
                                            @if ($referral->status === \App\Models\Referral::STATUS_REWARDED)
                                                <span class="badge bg-success-subtle text-success">Bonus diterima</span>
                                            @else
                                                <span class="badge bg-warning-subtle text-warning">Menunggu</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            Belum ada yang daftar lewat link Anda. Yuk, mulai bagikan!
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $referrals->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
