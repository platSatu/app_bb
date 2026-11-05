@extends('layouts.dashboard')

@section('title', 'Google Places')
@section('page-section', 'Tools')

@section('content')
    @unless ($configured)
        <div class="alert alert-warning">
            <i class="ri-key-2-line me-1"></i>API key Google Places belum diisi. Tambahkan <code>GOOGLE_PLACES_API_KEY</code> di file <code>.env</code>, lalu muat ulang halaman ini.
        </div>
    @endunless

    @if ($error)
        <div class="alert alert-danger"><i class="ri-error-warning-line me-1"></i>{{ $error }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-2" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                <div class="flex-grow-1" style="min-width: 220px">
                    <label class="form-label small text-muted mb-1">Jenis usaha</label>
                    <input type="text" name="keyword" class="form-control" maxlength="100" required
                           placeholder="mis. tempat kursus bahasa inggris" value="{{ $keyword }}">
                </div>
                <div class="flex-grow-1" style="min-width: 200px">
                    <label class="form-label small text-muted mb-1">Lokasi</label>
                    <input type="text" name="location" class="form-control" maxlength="100"
                           placeholder="mis. Tebet, Jakarta Selatan" value="{{ $location }}">
                </div>
                <button type="submit" class="btn btn-primary text-nowrap" @disabled(! $configured)><i class="ri-search-line"></i> Cari</button>
            </form>
            <p class="text-muted small mb-0">
                Pencarian hari ini: <strong>{{ $usedToday }}</strong> / {{ $dailyLimit }}.
                Setiap klik "Cari" atau "Halaman berikutnya" dihitung 1 pencarian dan ditagih Google.
                Hasil tidak disimpan; nomor telepon kantor (021, dll.) tidak bisa dihubungi lewat WhatsApp.
            </p>
        </div>
    </div>

    @if ($result)
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">{{ count($result['places']) }} tempat ditemukan</h6>
                    @if ($result['next_page_token'])
                        <a href="{{ route('superadmin.tools.google-places.index', ['keyword' => $keyword, 'location' => $location, 'page_token' => $result['next_page_token']]) }}"
                           class="btn btn-sm btn-outline-primary">Halaman berikutnya <i class="ri-arrow-right-line"></i></a>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr><th>#</th><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Rating</th><th class="text-end">Aksi</th></tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($result['places'] as $i => $place)
                                <tr @class(['opacity-50' => $place['closed']])>
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td class="fw-semibold" style="min-width: 180px">
                                        {{ $place['name'] }}
                                        @if ($place['closed']) <span class="badge bg-secondary ms-1">Tutup</span> @endif
                                    </td>
                                    <td style="min-width: 240px">{{ $place['address'] ?? '-' }}</td>
                                    <td class="text-nowrap">{{ $place['phone'] ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($place['rating'])
                                            <i class="ri-star-fill text-warning"></i> {{ number_format($place['rating'], 1) }}
                                            <span class="text-muted">({{ number_format($place['rating_count'], 0, ',', '.') }})</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if ($place['whatsapp'])
                                            <a href="https://wa.me/{{ $place['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Buka WhatsApp"><i class="ri-whatsapp-line"></i></a>
                                        @endif
                                        @if ($place['website'])
                                            <a href="{{ $place['website'] }}" target="_blank" rel="noopener nofollow" class="btn btn-sm btn-outline-secondary" title="Website"><i class="ri-global-line"></i></a>
                                        @endif
                                        @if ($place['maps_url'])
                                            <a href="{{ $place['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Buka di Google Maps"><i class="ri-map-pin-line"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada hasil. Coba kata kunci atau lokasi lain.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">Data dari Google Maps.</p>
            </div>
        </div>
    @endif
@endsection
