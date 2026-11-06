@extends('layouts.dashboard')

@section('title', $provider === 'open' ? 'Open Places' : 'Google Places')
@section('page-section', 'Tools')

@section('content')
    @unless ($configured)
        <div class="alert alert-warning">
            <i class="ri-key-2-line me-1"></i>API key {{ \App\Services\Marketing\GooglePlacesClient::PROVIDERS[$provider] }} belum diisi. Tambahkan <code>{{ $provider === 'open' ? 'OPEN_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY' }}</code> di file <code>.env</code>, lalu muat ulang halaman ini.
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
                Pencarian hari ini ({{ \App\Services\Marketing\GooglePlacesClient::PROVIDERS[$provider] }}): <strong>{{ $usedToday }}</strong> / {{ $dailyLimit }}.
                {{ $provider === 'open'
                    ? 'Open Places: gratis 10.000 pencarian/bulan, maks. 20 hasil dalam radius ±16 km dari lokasi, tanpa rating. Lokasi wajib diisi.'
                    : 'Setiap klik "Cari" atau "Halaman berikutnya" dihitung 1 pencarian dan ditagih Google.' }}
                Hasil tidak disimpan; nomor telepon kantor (021, dll.) tidak bisa dihubungi lewat WhatsApp.
            </p>
        </div>
    </div>

    @if ($result)
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-0">{{ count($result['places']) }} tempat ditemukan</h6>
                        @if (! empty($result['hidden']))
                            <div class="small text-muted mt-1">
                                {{ $result['hidden'] }} hasil lain disembunyikan karena nama/kategorinya tidak memuat "{{ $keyword }}" sebagai kata utuh.
                                <a href="{{ route('superadmin.tools.open-places.index', ['keyword' => $keyword, 'location' => $location, 'all' => 1]) }}">Tampilkan semua</a>
                            </div>
                        @elseif (request()->boolean('all') && $provider === 'open')
                            <div class="small text-muted mt-1">Menampilkan semua hasil, termasuk yang namanya hanya mirip. Centang yang relevan saja.</div>
                        @endif
                    </div>
                    @if ($result['next_page_token'])
                        <a href="{{ route('superadmin.tools.google-places.index', ['keyword' => $keyword, 'location' => $location, 'page_token' => $result['next_page_token']]) }}"
                           class="btn btn-sm btn-outline-primary">Halaman berikutnya <i class="ri-arrow-right-line"></i></a>
                    @endif
                </div>

                @php($open = $provider === 'open')
                {{-- Open Places: centang lalu unduh ke Excel (dari hasil yang tampil, tidak memakai kuota). --}}
                @if ($open && count($result['places']))
                    <form method="POST" action="{{ route('superadmin.tools.open-places.export') }}" id="places-export" class="d-flex justify-content-end gap-2 mb-2">
                        @csrf
                        <button type="submit" formaction="{{ route('superadmin.tools.open-places.save') }}" class="btn btn-sm btn-primary"><i class="ri-bookmark-line"></i> Simpan ke Prospek (<span data-selected>{{ count($result['places']) }}</span>)</button>
                        <button type="submit" class="btn btn-sm btn-success"><i class="ri-file-excel-2-line"></i> Download Excel</button>
                    </form>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-nowrap">
                            <tr>
                                @if ($open)
                                    <th><input type="checkbox" class="form-check-input" data-check-all checked title="Pilih semua"></th>
                                @endif
                                <th>#</th><th>Nama</th><th>Alamat</th><th>Telepon</th>
                                <th>{{ $open ? 'Jarak' : 'Rating' }}</th><th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse ($result['places'] as $i => $place)
                                <tr @class(['opacity-50' => $place['closed']])>
                                    @if ($open)
                                        <td><input type="checkbox" class="form-check-input" name="rows[]" form="places-export" value="{{ json_encode($place) }}" checked></td>
                                    @endif
                                    <td class="text-muted">{{ $i + 1 }}</td>
                                    <td class="fw-semibold" style="min-width: 180px">
                                        {{ $place['name'] }}
                                        @if ($place['closed']) <span class="badge bg-secondary ms-1">Tutup</span> @endif
                                        @if (! empty($place['category'])) <div class="text-muted fw-normal">{{ $place['category'] }}</div> @endif
                                    </td>
                                    <td style="min-width: 240px">
                                        {{ $place['address'] ?? '-' }}
                                        @if ($open && ($place['city'] || $place['province']))
                                            <div class="text-muted">{{ collect([$place['city'], $place['province'], $place['postal_code']])->filter()->implode(', ') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $place['phone'] ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        @if ($open)
                                            {{ $place['distance_km'] !== null ? $place['distance_km'].' km' : '-' }}
                                        @elseif ($place['rating'])
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
                                <tr><td colspan="{{ $open ? 7 : 6 }}" class="text-center text-muted py-4">Tidak ada hasil yang cocok. Coba kata yang biasa ada di nama tempat (kursus, bimbel, LPK, school, english, education) atau lokasi lain.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">{{ $open
                    ? 'Data dari Open Places API (Overture Maps). Hanya tempat yang namanya / kategorinya memuat kata kunci secara utuh. Excel berisi kolom lengkap: kategori, alamat, kota, provinsi, kode pos, telepon, WhatsApp, website, koordinat, jarak, link peta.'
                    : 'Data dari Google Maps. Ketentuan Google tidak mengizinkan data Places diunduh / disimpan.' }}</p>
            </div>
        </div>

        @if ($open)
            <script>
                (function () {
                    var all = document.querySelector('[data-check-all]');
                    var boxes = Array.prototype.slice.call(document.querySelectorAll('input[name="rows[]"]'));
                    var count = document.querySelector('[data-selected]');
                    var buttons = Array.prototype.slice.call(document.querySelectorAll('#places-export button'));
                    var form = document.getElementById('places-export');
                    if (!all || !form) return;
                    var update = function () {
                        var n = boxes.filter(function (b) { return b.checked; }).length;
                        count.textContent = n;
                        buttons.forEach(function (b) { b.disabled = n === 0; });
                        all.checked = n === boxes.length;
                        all.indeterminate = n > 0 && n < boxes.length;
                    };
                    all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
                    boxes.forEach(function (b) { b.addEventListener('change', update); });
                    update();
                })();
            </script>
        @endif
    @endif
@endsection
