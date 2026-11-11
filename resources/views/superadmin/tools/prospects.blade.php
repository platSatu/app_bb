@extends('layouts.dashboard')

@section('title', 'Prospek')
@section('page-section', 'Tools')

@php
    use App\Models\Prospect;
@endphp

@push('styles')
    <style>
        .prospect-table { font-size: .875rem; }
        .prospect-table th { font-weight: 600; white-space: nowrap; }
        .prospect-table .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); margin-top: 2px; }
        .prospect-edit td { background: var(--bs-tertiary-bg, #f8f9fa); }
    </style>
@endpush

@section('content')
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ ($tab ?? 'list') === 'list' ? 'active' : '' }}" href="{{ route('superadmin.tools.prospects.index') }}"><i class="ri-contacts-book-line me-1"></i>Prospek</a></li>
        <li class="nav-item"><a class="nav-link {{ ($tab ?? 'list') === 'outreach' ? 'active' : '' }}" href="{{ route('superadmin.tools.prospects.outreach') }}"><i class="ri-mail-check-line me-1"></i>Persetujuan
            @if ($draftCount = Prospect::where('outreach_status', 'draft')->count()) <span class="badge bg-warning text-dark ms-1">{{ $draftCount }}</span> @endif</a></li>
        <li class="nav-item"><a class="nav-link {{ ($tab ?? 'list') === 'agent' ? 'active' : '' }}" href="{{ route('superadmin.tools.prospects.agent') }}"><i class="ri-robot-2-line me-1"></i>Agent</a></li>
    </ul>

    @if (($tab ?? 'list') === 'agent')
        {{-- Agent Prospek (App\Services\Marketing\ProspectAgent): setiap hari mencari & menilai prospek, belum mengirim pesan. --}}
        <div class="row g-3">
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-3">Pengaturan</h6>
                        <form method="POST" action="{{ route('superadmin.tools.prospects.agent.update') }}" class="row g-3">
                            @csrf
                            @method('PUT')
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="agent-active" name="is_active" value="1" @checked(old('is_active', $setting->is_active))>
                                    <label class="form-check-label fw-semibold" for="agent-active">Agent aktif (jalan otomatis setiap hari)</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Segmen & kata kunci</label>
                                <textarea name="segments" rows="6" class="form-control font-monospace small" required maxlength="3000">{{ old('segments', $setting->segmentsText()) }}</textarea>
                                <div class="form-text">Satu baris per segmen: <code>Nama segmen: kata kunci 1, kata kunci 2</code> (maks 20 segmen, 6 kata kunci).</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Kota / area</label>
                                <textarea name="cities" rows="5" class="form-control small" required maxlength="2000">{{ old('cities', implode("\n", $setting->cities ?? [])) }}</textarea>
                                <div class="form-text">Satu kota per baris, mis. "Tebet, Jakarta Selatan".</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Prospek baru / hari</label>
                                <input type="number" name="daily_quota" class="form-control" min="1" max="200" required value="{{ old('daily_quota', $setting->daily_quota) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Jam jalan (WIB)</label>
                                <input type="time" name="run_at" class="form-control" required value="{{ old('run_at', $setting->run_at) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email laporan harian</label>
                                <input type="email" name="report_email" class="form-control" maxlength="150" value="{{ old('report_email', $setting->report_email) }}" placeholder="kosongkan kalau tidak perlu">
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('superadmin.tools.prospects.agent.run') }}" class="mt-2" onsubmit="this.querySelector('button').disabled = true;">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="ri-play-line"></i> Jalankan sekarang (uji coba)</button>
                        </form>
                        <div class="small text-muted mt-3">
                            Penilaian: {!! \App\Models\AiProvider::active() ? '<span class="text-success">AI ('.e(\App\Models\AiProvider::active()->name).')</span>' : 'aturan sederhana (isi API key di Superadmin › AI supaya dinilai AI)' !!}.
                            Maks 3 giliran per hari; kuota Open Places harian tetap berlaku. Email hanya dikirim setelah disetujui di tab Persetujuan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-3">Riwayat</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 prospect-table">
                                <thead class="table-light"><tr><th>Waktu</th><th>Segmen / kata kunci</th><th>Kota</th><th class="text-end">Ditemukan</th><th class="text-end">Baru</th></tr></thead>
                                <tbody>
                                    @forelse ($setting->runs ?? [] as $run)
                                        <tr>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($run['at'])->timezone(config('app.timezone'))->translatedFormat('d M H:i') }}</td>
                                            <td>{{ $run['segment'] }}<span class="sub">{{ $run['keyword'] }}{{ $run['ai'] ? ' · dinilai AI' : '' }}</span>
                                                @if ($run['error']) <span class="sub text-danger">{{ $run['error'] }}</span> @endif</td>
                                            <td>{{ $run['city'] }}</td>
                                            <td class="text-end">{{ $run['found'] }}</td>
                                            <td class="text-end fw-semibold">{{ $run['saved'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">Belum pernah berjalan.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h6 class="mb-1">Template email pembuka</h6>
                                <div class="small text-muted">Dipakai untuk draf prospek yang punya email. <code>{nama}</code> <code>{kota}</code> <code>{segmen}</code> diganti otomatis;
                                    kalau AI aktif, AI menyesuaikan tiap draf dari template ini. Kalimat "{{ \App\Services\Marketing\ProspectAgent::STOP_LINE }}" selalu ditambahkan di akhir.</div>
                            </div>
                            @if (\App\Models\AiProvider::active())
                                <form method="POST" action="{{ route('superadmin.tools.prospects.agent.templates.ai') }}" onsubmit="this.querySelector('button').disabled = true;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="ri-sparkling-line"></i> Buatkan dengan AI</button>
                                </form>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('superadmin.tools.prospects.agent.templates') }}" class="row g-3">
                            @csrf
                            @method('PUT')
                            {{-- Baris sendiri, supaya template segmen di bawahnya rata 2 kolom. --}}
                            <div class="col-12">
                                <label class="form-label">Skor minimal untuk dibuatkan draf</label>
                                <input type="number" name="min_score" class="form-control" style="max-width: 160px" min="0" max="100" required value="{{ old('min_score', $setting->min_score) }}">
                            </div>
                            @foreach ($setting->segments ?? [] as $i => $segment)
                                @php($template = $setting->template($segment['name']))
                                <div class="col-12 col-lg-6">
                                    <input type="hidden" name="templates[{{ $i }}][segment]" value="{{ $segment['name'] }}">
                                    <label class="form-label fw-semibold">{{ $segment['name'] }}</label>
                                    <input type="text" name="templates[{{ $i }}][subject]" class="form-control form-control-sm mb-2" maxlength="200" required value="{{ old("templates.$i.subject", $template['subject']) }}" placeholder="Subjek">
                                    <textarea name="templates[{{ $i }}][body]" rows="8" class="form-control form-control-sm" maxlength="5000" required>{{ old("templates.$i.body", $template['body']) }}</textarea>
                                </div>
                            @endforeach
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan template</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @elseif (($tab ?? 'list') === 'outreach')
        {{-- Draf email pembuka dari agent. Disetujui -> dikirim lewat Email Blast (Senin-Jumat 09.00-16.00, batas harian Email Blast). --}}
        <div class="card">
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Email yang disetujui dikirim otomatis Senin–Jumat 09.00–16.00 WIB lewat Email Blast (satu email per prospek, batas harian
                    {{ $blastSetting->daily_limit }} email, hasilnya di Tools › Email Blast › Terkirim). Menunggu dikirim: <b>{{ $approvedCount }}</b>.
                    @unless ($blastSetting->isConfigured()) <span class="text-danger">Akun pengirim Email Blast belum diatur.</span> @endunless
                </div>

                @if ($drafts->isNotEmpty())
                    <form method="POST" action="{{ route('superadmin.tools.prospects.outreach.bulk') }}" id="bulk-outreach" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                        @csrf
                        <div class="form-check me-2">
                            <input class="form-check-input" type="checkbox" id="check-all" onclick="document.querySelectorAll('.draft-check').forEach(c => c.checked = this.checked)">
                            <label class="form-check-label small" for="check-all">Pilih semua di halaman ini</label>
                        </div>
                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Setujui semua draf terpilih apa adanya?')"><i class="ri-check-double-line"></i> Setujui terpilih</button>
                        <button type="submit" name="action" value="skip" class="btn btn-sm btn-outline-secondary">Lewati terpilih</button>
                    </form>
                @endif

                @forelse ($drafts as $draft)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div class="form-check">
                                <input class="form-check-input draft-check" type="checkbox" name="ids[]" value="{{ $draft->id }}" form="bulk-outreach" id="d-{{ $draft->id }}">
                                <label class="form-check-label fw-semibold" for="d-{{ $draft->id }}">{{ $draft->name }}</label>
                                <span class="small text-muted d-block">{{ $draft->email }} · {{ collect([$draft->segment, $draft->city])->filter()->implode(' · ') }}</span>
                            </div>
                            <div class="text-end small">
                                <span class="badge {{ $draft->score >= 70 ? 'bg-success' : 'bg-warning text-dark' }}">{{ $draft->score }}</span>
                                <span class="d-block text-muted" style="max-width: 320px">{{ $draft->score_reason }}</span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('superadmin.tools.prospects.outreach.update', $draft) }}">
                            @csrf
                            @method('PUT')
                            <input type="text" name="subject" class="form-control form-control-sm mb-2" maxlength="200" value="{{ $draft->outreach_subject }}">
                            <textarea name="body" rows="7" class="form-control form-control-sm" maxlength="5000">{{ $draft->outreach_body }}</textarea>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success"><i class="ri-check-line"></i> Simpan & setujui</button>
                                <button type="submit" name="action" value="skip" class="btn btn-sm btn-outline-secondary">Lewati</button>
                                @if ($draft->website) <a href="{{ $draft->website }}" target="_blank" rel="noopener nofollow" class="btn btn-sm btn-link">Lihat website</a> @endif
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Belum ada draf. Agent membuat draf untuk prospek Baru yang punya email & skornya cukup.</div>
                @endforelse

                {{ $drafts->links() }}
            </div>
        </div>
    @else
    <div class="card">
        <div class="card-body">
            {{-- Jumlah per status; klik untuk menyaring. --}}
            <ul class="nav nav-pills mb-3 flex-wrap">
                <li class="nav-item">
                    <a class="nav-link {{ request('status') ? '' : 'active' }}" href="{{ route('superadmin.tools.prospects.index', request()->except('status', 'page')) }}">Semua <span class="badge bg-light text-dark ms-1">{{ $counts->sum() }}</span></a>
                </li>
                @foreach (Prospect::STATUSES as $key => [$label])
                    <li class="nav-item">
                        <a class="nav-link {{ request('status') === $key ? 'active' : '' }}" href="{{ route('superadmin.tools.prospects.index', ['status' => $key] + request()->except('status', 'page')) }}">{{ $label }} <span class="badge bg-light text-dark ms-1">{{ $counts[$key] ?? 0 }}</span></a>
                    </li>
                @endforeach
            </ul>

            <form method="GET" class="row g-2 align-items-end mb-3">
                @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" maxlength="100" value="{{ request('search') }}" placeholder="Nama, kategori, telepon, alamat">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Segmen</label>
                    <select name="segment" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($segments as $segment)
                            <option value="{{ $segment }}" @selected(request('segment') === $segment)>{{ $segment }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1">Kecamatan / Kota</label>
                    <select name="city" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1" title="Filter"><i class="ri-search-line"></i></button>
                    <a href="{{ route('superadmin.tools.prospects.export', request()->except('page')) }}" class="btn btn-success" title="Download Excel (sesuai filter)"><i class="ri-file-excel-2-line"></i></a>
                    @if (request()->hasAny(['search', 'city', 'status', 'segment', 'sort']))
                        <a href="{{ route('superadmin.tools.prospects.index') }}" class="btn btn-outline-danger" title="Hapus filter"><i class="ri-close-line"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 prospect-table">
                    <thead class="table-light">
                        <tr><th>Nama</th><th>Alamat</th><th>Kontak</th>
                            <th class="text-nowrap"><a href="{{ route('superadmin.tools.prospects.index', ['sort' => request('sort') === 'score' ? null : 'score'] + request()->except('sort', 'page')) }}" class="text-reset" title="Urutkan skor tertinggi">Skor <i class="ri-arrow-down-line"></i></a></th>
                            <th>Rating</th><th>Status</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($prospects as $prospect)
                            <tr @class(['opacity-50' => $prospect->is_closed])>
                                <td style="min-width: 200px">
                                    <span class="fw-semibold">{{ $prospect->name }}</span>
                                    @if ($prospect->is_closed) <span class="badge bg-secondary ms-1">Tutup</span> @endif
                                    <span class="sub">{{ $prospect->category ?: '-' }}@if ($prospect->segment) · {{ $prospect->segment }}@endif</span>
                                </td>
                                <td style="min-width: 220px">
                                    {{ $prospect->address ?: '-' }}
                                    <span class="sub">{{ collect([$prospect->city, $prospect->province, $prospect->postal_code])->filter()->implode(', ') }}</span>
                                </td>
                                <td class="text-nowrap">
                                    {{ $prospect->phone ?: '-' }}
                                    @if ($prospect->email) <span class="sub">{{ $prospect->email }}</span> @endif
                                    @if ($prospect->opening_hours) <span class="sub"><i class="ri-time-line"></i> {{ $prospect->opening_hours }}</span> @endif
                                </td>
                                <td style="max-width: 200px">
                                    @if ($prospect->score !== null)
                                        <span class="badge {{ $prospect->score >= 70 ? 'bg-success' : ($prospect->score >= 50 ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $prospect->score }}</span>
                                        <span class="sub">{{ $prospect->score_reason }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($prospect->rating)
                                        <i class="ri-star-fill text-warning"></i> {{ number_format($prospect->rating, 1) }}
                                        <span class="text-muted">({{ number_format((int) $prospect->rating_count, 0, ',', '.') }})</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $prospect->statusBadge() }}">{{ $prospect->statusLabel() }}</span>
                                    @if ($outreach = Prospect::OUTREACH[$prospect->outreach_status] ?? null) <span class="badge {{ $outreach[1] }}">{{ $outreach[0] }}</span> @endif
                                    @if ($prospect->notes) <span class="sub text-truncate" style="max-width: 200px" title="{{ $prospect->notes }}">{{ $prospect->notes }}</span> @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($prospect->whatsapp)
                                        <a href="https://wa.me/{{ $prospect->whatsapp }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="ri-whatsapp-line"></i></a>
                                    @endif
                                    @if ($prospect->website)
                                        <a href="{{ $prospect->website }}" target="_blank" rel="noopener nofollow" class="btn btn-sm btn-outline-secondary" title="Website"><i class="ri-global-line"></i></a>
                                    @endif
                                    @if ($prospect->maps_url)
                                        <a href="{{ $prospect->maps_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Buka di Google Maps (lihat rating, ulasan, jam buka)"><i class="ri-map-pin-line"></i></a>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#edit-{{ $prospect->id }}" title="Ubah status & lengkapi data"><i class="ri-edit-line"></i></button>
                                </td>
                            </tr>
                            <tr class="collapse prospect-edit" id="edit-{{ $prospect->id }}">
                                <td colspan="7">
                                    <form method="POST" action="{{ route('superadmin.tools.prospects.update', $prospect) }}" class="row g-2 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small text-muted mb-1">Status</label>
                                            <select name="status" class="form-select form-select-sm">
                                                @foreach (Prospect::STATUSES as $key => [$label])
                                                    <option value="{{ $key }}" @selected($prospect->status === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small text-muted mb-1">Telepon / WA</label>
                                            <input type="text" name="phone" class="form-control form-control-sm" maxlength="30" value="{{ $prospect->phone }}">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small text-muted mb-1">Rating</label>
                                            <input type="number" name="rating" class="form-control form-control-sm" min="0" max="5" step="0.1" value="{{ $prospect->rating }}">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small text-muted mb-1">Jumlah ulasan</label>
                                            <input type="number" name="rating_count" class="form-control form-control-sm" min="0" value="{{ $prospect->rating_count }}">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small text-muted mb-1">Jam buka</label>
                                            <input type="text" name="opening_hours" class="form-control form-control-sm" maxlength="300" value="{{ $prospect->opening_hours }}" placeholder="mis. Senin–Sabtu 08.00–20.00">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label small text-muted mb-1">Email</label>
                                            <input type="email" name="email" class="form-control form-control-sm" maxlength="150" value="{{ $prospect->email }}">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label small text-muted mb-1">Website</label>
                                            <input type="url" name="website" class="form-control form-control-sm" maxlength="300" value="{{ $prospect->website }}" placeholder="https://">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small text-muted mb-1">Catatan</label>
                                            <input type="text" name="notes" class="form-control form-control-sm" maxlength="2000" value="{{ $prospect->notes }}" placeholder="mis. minta demo minggu depan">
                                        </div>
                                        <div class="col-12 col-md-2 d-flex gap-2">
                                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Simpan</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('superadmin.tools.prospects.destroy', $prospect) }}" class="mt-2" onsubmit="return confirm('Hapus prospek {{ e(addslashes($prospect->name)) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="ri-delete-bin-line"></i> Hapus prospek ini</button>
                                        @if ($prospect->contacted_at) <span class="small text-muted ms-3">Pertama dihubungi {{ $prospect->contacted_at->translatedFormat('d M Y H:i') }}</span> @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada prospek. Simpan dari Tools › Open Places atau nyalakan Agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $prospects->links() }}</div>

            <div class="small text-muted mt-2">
                Rating, jumlah ulasan & jam buka diisi manual (lihat lewat tombol peta), karena data Google tidak boleh disimpan otomatis.
                Excel berisi semua kolom sesuai filter.
            </div>
        </div>
    </div>
    @endif
@endsection
