@extends('layouts.dashboard')

@section('title', 'Konten Harian')
@section('page-section', 'Tools')

@php
    use App\Models\ContentPost;
    use App\Models\ContentPublication;
    use App\Models\SocialAccount;

    $plan = $setting->plan_state ?? [];
    $planRunning = $setting->planRunning();
    $connected = $accounts->where('status', SocialAccount::STATUS_CONNECTED);
    $privacyLabels = ['PUBLIC_TO_EVERYONE' => 'Publik', 'MUTUAL_FOLLOW_FRIENDS' => 'Teman (saling ikuti)', 'FOLLOWER_OF_CREATOR' => 'Pengikut', 'SELF_ONLY' => 'Hanya saya'];
    $tiktokOptions = $connected->where('platform', 'tiktok')->flatMap(fn ($a) => $a->tiktokPrivacyOptions())->unique()->values();
@endphp

@push('styles')
    <style>
        .content-day { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color, #6c757d); margin: 1.25rem 0 .5rem; }
        .content-item { border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .5rem; padding: .85rem 1rem; margin-bottom: .6rem; background: var(--bs-body-bg, #fff); }
        .content-item.is-checked { border-color: var(--bs-primary, #0d6efd); box-shadow: 0 0 0 1px var(--bs-primary, #0d6efd) inset; }
        .content-item .hook { font-size: .85rem; color: var(--bs-secondary-color, #6c757d); }
        .content-thumb { width: 96px; height: 120px; object-fit: cover; border-radius: .375rem; background: #18223B; }
        .content-text { white-space: pre-wrap; font-size: .85rem; background: var(--bs-tertiary-bg, #f8f9fa); border-radius: .375rem; padding: .6rem .75rem; }
        .pub-line { font-size: .8rem; }
        .bulk-bar { position: sticky; top: 0; z-index: 5; background: var(--bs-body-bg, #fff); padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color, #dee2e6); }
    </style>
@endpush

@section('content')
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'list' ? 'active' : '' }}" href="{{ route('superadmin.tools.content.index') }}"><i class="ri-calendar-schedule-line me-1"></i>Konten</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'settings' ? 'active' : '' }}" href="{{ route('superadmin.tools.content.settings') }}"><i class="ri-robot-2-line me-1"></i>Buat Rencana & Pengaturan</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('superadmin.tools.social.index') }}"><i class="ri-share-line me-1"></i>Akun Sosmed</a></li>
    </ul>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="ri-error-warning-line"></i> {{ $errors->first() }}</div>
    @endif

    @if ($planRunning)
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" id="plan-progress" data-url="{{ route('superadmin.tools.content.plan.status') }}">
            <span class="spinner-border spinner-border-sm"></span>
            <span>Agent sedang membuat rencana: <b data-plan-done>{{ (int) ($plan['done'] ?? 0) }}</b> dari {{ (int) ($plan['total'] ?? 0) }} konten jadi.
                Daftar di bawah diperbarui otomatis.</span>
            <form method="POST" action="{{ route('superadmin.tools.content.plan.stop') }}" class="ms-auto" onsubmit="return confirm('Hentikan pembuatan rencana? Konten yang sudah jadi tetap ada.')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Hentikan</button>
            </form>
        </div>
    @elseif (! empty($plan['updated_at']) && now()->subDay()->lt($plan['updated_at']) && ($plan['failed'] ?? 0) > 0)
        <div class="alert alert-warning small">
            Rencana terakhir: {{ (int) ($plan['done'] ?? 0) }} konten jadi, {{ (int) $plan['failed'] }} gagal dibuat AI.
            @if (! empty($plan['error'])) Penyebab: {{ $plan['error'] }} @endif
            Buat ulang dari tab Buat Rencana (tanggal yang sudah terisi dilewati).
        </div>
    @endif

    @unless ($mediaReady)
        <div class="alert alert-warning small"><i class="ri-information-line"></i> Pembuatan gambar & video otomatis butuh penyedia AI <b>Classai Router</b> yang aktif di Settings › AI. Media tetap bisa diunggah sendiri.</div>
    @endunless

    @if ($tab === 'settings')
        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-1">Buat rencana konten</h6>
                        <p class="small text-muted">AI menyusun konten per tanggal & jam tayang sesuai pilar dan pengaturan di samping. Semua masuk sebagai <b>Draf</b>;
                            tidak ada yang dibuatkan media atau diposting sebelum Anda setujui. Tanggal & jam yang sudah punya konten dilewati.</p>
                        <form method="POST" action="{{ route('superadmin.tools.content.plan') }}" class="row g-2" onsubmit="this.querySelector('button').disabled = true;">
                            @csrf
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Mulai tanggal</label>
                                <input type="date" name="start_date" class="form-control" required min="{{ today()->toDateString() }}" value="{{ old('start_date', today()->addDay()->toDateString()) }}">
                            </div>
                            <div class="col-7">
                                <label class="form-label small text-muted mb-1">Lama</label>
                                <select name="days" class="form-select">
                                    @foreach ([7 => '1 minggu (7 hari)', 14 => '2 minggu (14 hari)', 30 => '1 bulan (30 hari)'] as $days => $label)
                                        <option value="{{ $days }}" @selected((int) old('days', 7) === $days)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label small text-muted mb-1">Konten / hari</label>
                                <select name="per_day" class="form-select">
                                    @foreach ([1, 2, 3] as $n)
                                        <option value="{{ $n }}" @selected((int) old('per_day', count($setting->times())) === $n)>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100" @disabled($planRunning)><i class="ri-magic-line"></i> Buat rencana</button>
                            </div>
                        </form>
                        <div class="small text-muted mt-2">Jam tayang: {{ implode(', ', $setting->times()) }} WIB. Biaya AI teks kecil; biaya terbesar ada di media (lihat di bawah).</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body small">
                        <h6 class="mb-2">Perkiraan biaya media (Classai)</h6>
                        @foreach (ContentPost::FORMATS as $format => [$label, $icon])
                            <div class="d-flex justify-content-between"><span><i class="{{ $icon }}"></i> {{ $label }}</span><span>{{ $prices[$format] ?? '-' }}</span></div>
                        @endforeach
                        <div class="text-muted mt-2">Media dibuat setelah konten disetujui, paling banyak <b>{{ $setting->media_daily_limit }}</b> konten per hari (sisa hari ini: {{ $remaining }}).
                            Gambar AI kadang salah eja tulisan: cek pratinjau sebelum menjadwalkan.</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-8">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Pengaturan Agent Konten</h6>
                        <form method="POST" action="{{ route('superadmin.tools.content.settings.update') }}" class="row g-3">
                            @csrf
                            @method('PUT')
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Pilar konten <span class="text-muted">(satu baris: <code>Nama: keterangan</code>, digilir otomatis)</span></label>
                                <textarea name="pillars" rows="5" class="form-control" maxlength="4000" required>{{ old('pillars', $setting->pillarsText()) }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Gaya bahasa</label>
                                <textarea name="brand_voice" rows="3" class="form-control" maxlength="1000">{{ old('brand_voice', $setting->brand_voice) }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Target pembaca</label>
                                <textarea name="audience" rows="3" class="form-control" maxlength="1000">{{ old('audience', $setting->audience) }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Fakta & ajakan yang boleh disebut <span class="text-muted">(AI tidak mengarang di luar ini)</span></label>
                                <textarea name="offer" rows="3" class="form-control" maxlength="1500">{{ old('offer', $setting->offer) }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Gaya visual (warna, suasana)</label>
                                <textarea name="visual_style" rows="3" class="form-control" maxlength="1000">{{ old('visual_style', $setting->visual_style) }}</textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Campuran format (%)</label>
                                <div class="input-group">
                                    @foreach (ContentPost::FORMATS as $format => [$label])
                                        <span class="input-group-text small">{{ $label }}</span>
                                        <input type="number" name="formats[{{ $format }}]" class="form-control" min="0" max="100" value="{{ old('formats.'.$format, $setting->formatMix()[$format] ?? 0) }}">
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Jam tayang (WIB, maks 3, pisahkan koma)</label>
                                <input type="text" name="post_times" class="form-control" maxlength="100" required value="{{ old('post_times', implode(', ', $setting->times())) }}" placeholder="11:30, 19:00">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="auto_media" value="1" id="auto_media" @checked(old('auto_media', $setting->auto_media))>
                                    <label class="form-check-label" for="auto_media">Buat media otomatis setelah konten disetujui</label>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Model gambar (gambar & carousel)</label>
                                <select name="image_model" class="form-select">
                                    @foreach ($imageModels as $model)
                                        <option value="{{ $model['id'] }}" @selected(old('image_model', $setting->imageModel()) === $model['id'])>{{ $model['name'] }}{{ $model['price'] ? ' · '.$model['price'] : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small text-muted mb-1">Model video (Reels, 9:16)</label>
                                <select name="video_model" class="form-select">
                                    @foreach ($videoModels as $model)
                                        @continue(! in_array('9:16', $model['aspects'] ?? [], true))
                                        <option value="{{ $model['id'] }}" @selected(old('video_model', $setting->videoModel()) === $model['id'])>{{ $model['name'] }} · {{ implode('/', $model['resolutions']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small text-muted mb-1">Resolusi video</label>
                                <select name="video_resolution" class="form-select">
                                    @foreach (collect($videoModels)->pluck('resolutions')->flatten()->unique()->sort()->values() as $res)
                                        <option value="{{ $res }}" @selected(old('video_resolution', $setting->videoResolution()) === $res)>{{ $res }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Facebook Reels minimal 540×960: pilih 720p ke atas.</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small text-muted mb-1">Durasi video (detik)</label>
                                <input type="number" name="video_seconds" class="form-control" min="3" max="30" value="{{ old('video_seconds', $setting->video_seconds) }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small text-muted mb-1">Slide carousel</label>
                                <input type="number" name="carousel_slides" class="form-control" min="3" max="{{ ContentPost::MAX_SLIDES }}" value="{{ old('carousel_slides', $setting->carousel_slides) }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small text-muted mb-1">Media maks / hari</label>
                                <input type="number" name="media_daily_limit" class="form-control" min="0" max="200" value="{{ old('media_daily_limit', $setting->media_daily_limit) }}">
                            </div>
                            <div class="col-12 small text-muted">
                                Akun tujuan bawaan untuk "Jadwalkan terpilih" diatur di <a href="{{ route('superadmin.tools.social.index') }}">Akun Sosmed</a> (centang "Tujuan bawaan").
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        @if ($connected->isEmpty())
            <div class="alert alert-light border small"><i class="ri-share-line"></i> Belum ada akun sosmed yang tersambung. Sambungkan Instagram, Facebook, TikTok atau YouTube di
                <a href="{{ route('superadmin.tools.social.index') }}">Akun Sosmed</a> supaya konten bisa dijadwalkan.</div>
        @endif

        <div class="card">
            <div class="card-body">
                <ul class="nav nav-pills mb-3 flex-wrap">
                    <li class="nav-item">
                        <a class="nav-link {{ $filters['status'] ? '' : 'active' }}" href="{{ route('superadmin.tools.content.index', array_filter(['status' => null] + $filters)) }}">Semua <span class="badge bg-light text-dark ms-1">{{ $counts->sum() }}</span></a>
                    </li>
                    @foreach (ContentPost::STATUSES as $key => [$label])
                        <li class="nav-item">
                            <a class="nav-link {{ $filters['status'] === $key ? 'active' : '' }}" href="{{ route('superadmin.tools.content.index', array_filter(['status' => $key] + $filters)) }}">{{ $label }} <span class="badge bg-light text-dark ms-1">{{ $counts[$key] ?? 0 }}</span></a>
                        </li>
                    @endforeach
                </ul>

                <form method="GET" class="row g-2 align-items-end mb-2">
                    @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1">Cari</label>
                        <input type="text" name="search" class="form-control" maxlength="100" value="{{ $filters['search'] }}" placeholder="Judul, caption, pilar">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Format</label>
                        <select name="format" class="form-select">
                            <option value="">Semua</option>
                            @foreach (ContentPost::FORMATS as $format => [$label])
                                <option value="{{ $format }}" @selected($filters['format'] === $format)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Tayang dari</label>
                        <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1">Sampai</label>
                        <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
                    </div>
                    <div class="col-6 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-search-line"></i></button>
                        <a href="{{ route('superadmin.tools.content.index', ['from' => '']) }}" class="btn btn-outline-secondary" title="Tampilkan semua tanggal">Semua tanggal</a>
                    </div>
                </form>

                {{-- Aksi massal: centang di tiap konten memakai form="bulk-form". "Semua hasil filter" mengirim filter yang sama (maks 500). --}}
                <form method="POST" action="{{ route('superadmin.tools.content.bulk') }}" id="bulk-form" class="bulk-bar mb-2"
                    data-total="{{ $total }}" onsubmit="return contentBulkSubmit(this, event)">
                    @csrf
                    <input type="hidden" name="all" value="0">
                    @foreach ($filters as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="form-check me-1">
                            <input class="form-check-input" type="checkbox" id="check-page">
                            <label class="form-check-label small" for="check-page">Pilih semua di halaman ini</label>
                        </div>
                        <span class="small text-muted" data-selected-info>0 dipilih</span>
                        @if ($total > $posts->count())
                            <button type="button" class="btn btn-sm btn-link p-0 d-none" data-select-all>Pilih semua {{ number_format(min($total, \App\Http\Controllers\Superadmin\Tools\ContentController::BULK_MAX), 0, ',', '.') }} konten sesuai filter</button>
                            <button type="button" class="btn btn-sm btn-link p-0 text-danger d-none" data-select-none>Batalkan pilihan semua</button>
                        @endif
                        <div class="vr d-none d-md-block"></div>
                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" data-confirm="Setujui konten terpilih? Artinya sudah Anda baca; media akan dibuat otomatis."><i class="ri-check-double-line"></i> Setujui</button>
                        <button type="submit" name="action" value="unapprove" class="btn btn-sm btn-outline-secondary" data-confirm="Kembalikan konten terpilih ke Draf?">Batal setujui</button>
                        <button type="submit" name="action" value="media" class="btn btn-sm btn-outline-primary" data-confirm="Buat media untuk konten terpilih (yang sudah disetujui)? Ada biaya Classai.">
                            <i class="ri-image-ai-line"></i> Buat media</button>
                        <button type="submit" name="action" value="schedule" class="btn btn-sm btn-warning" data-confirm="Jadwalkan konten terpilih yang siap upload ke akun tujuan bawaan, sesuai tanggal & jam tayangnya?">
                            <i class="ri-calendar-check-line"></i> Jadwalkan</button>
                        <button type="submit" name="action" value="unschedule" class="btn btn-sm btn-outline-warning" data-confirm="Batalkan jadwal konten terpilih?">Batalkan jadwal</button>
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" data-confirm="Hapus konten terpilih beserta medianya? Tidak bisa dikembalikan."><i class="ri-delete-bin-line"></i></button>
                    </div>
                    @if ($tiktokOptions->isNotEmpty())
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
                            <span class="text-muted"><i class="ri-tiktok-line"></i> TikTok untuk "Jadwalkan":</span>
                            <select name="tiktok_privacy" class="form-select form-select-sm w-auto">
                                <option value="">Pilih privasi…</option>
                                @foreach ($tiktokOptions as $option)
                                    <option value="{{ $option }}">{{ $privacyLabels[$option] ?? $option }}</option>
                                @endforeach
                            </select>
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" name="tiktok_comment" value="1"> Izinkan komentar</label>
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" name="tiktok_duet" value="1"> Duet</label>
                            <label class="form-check-label"><input type="checkbox" class="form-check-input" name="tiktok_stitch" value="1"> Stitch</label>
                        </div>
                    @endif
                </form>

                @forelse ($posts->groupBy(fn ($p) => $p->publish_date->toDateString()) as $date => $items)
                    <div class="content-day">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y') }}</div>

                    @foreach ($items as $post)
                        @php
                            $files = $post->mediaFiles();
                            $allowed = $connected->filter(fn ($a) => in_array($a->platform, $post->allowedPlatforms(), true));
                        @endphp
                        <div class="content-item" id="post-{{ $post->id }}">
                            <div class="d-flex gap-3">
                                <div class="pt-1">
                                    <input class="form-check-input post-check" type="checkbox" name="ids[]" value="{{ $post->id }}" form="bulk-form" aria-label="Pilih {{ $post->title }}">
                                </div>
                                <div class="flex-grow-1" style="min-width: 0">
                                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1 small">
                                        <span class="badge bg-dark">{{ $post->publish_time }}</span>
                                        <span class="badge bg-light text-dark border"><i class="{{ $post->formatIcon() }}"></i> {{ $post->formatLabel() }}</span>
                                        @if ($post->pillar) <span class="badge bg-light text-dark border">{{ $post->pillar }}</span> @endif
                                        <span class="badge {{ ContentPost::STATUSES[$post->status][1] ?? 'bg-light text-dark' }}">{{ ContentPost::STATUSES[$post->status][0] ?? $post->status }}</span>
                                        @if ($post->media_status)
                                            <span class="badge {{ ContentPost::MEDIA_STATUSES[$post->media_status][1] }}">{{ ContentPost::MEDIA_STATUSES[$post->media_status][0] }}</span>
                                        @endif
                                        @if ($post->scheduled_at && $post->status === 'scheduled')
                                            <span class="text-muted"><i class="ri-time-line"></i> {{ $post->scheduled_at->translatedFormat('d M H:i') }}</span>
                                        @endif
                                    </div>
                                    <div class="fw-semibold">{{ $post->title }}</div>
                                    @if ($post->hook) <div class="hook">“{{ $post->hook }}”</div> @endif
                                    @if ($post->media_error) <div class="small text-danger mt-1"><i class="ri-error-warning-line"></i> {{ $post->media_error }}</div> @endif

                                    @foreach ($post->publications as $pub)
                                        <div class="pub-line mt-1 d-flex flex-wrap align-items-center gap-2">
                                            <i class="{{ $pub->account?->platformIcon() ?? 'ri-share-line' }}" style="color: {{ $pub->account?->platformColor() ?? '#6c757d' }}"></i>
                                            <span>{{ $pub->account?->displayName() ?? ucfirst($pub->platform).' (akun dihapus)' }}</span>
                                            <span class="badge {{ $pub->statusBadge() }}">{{ $pub->statusLabel() }}</span>
                                            @if ($pub->permalink) <a href="{{ $pub->permalink }}" target="_blank" rel="noopener noreferrer">Lihat posting <i class="ri-external-link-line"></i></a> @endif
                                            @if ($pub->error) <span class="text-danger">{{ $pub->error }}</span> @endif
                                            @if ($pub->status === 'failed' && $pub->account)
                                                <form method="POST" action="{{ route('superadmin.tools.content.retry', $pub) }}" class="d-inline" onsubmit="return confirm('Sudah dicek di akunnya dan belum terposting? Kirim ulang ke {{ e(addslashes($pub->account->platformLabel())) }}.')">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-link p-0">Coba lagi</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#detail-{{ $post->id }}">
                                            <i class="ri-file-text-line"></i> {{ $post->isEditable() ? 'Baca / edit' : 'Lihat' }}</button>

                                        @if ($post->status === 'draft')
                                            <form method="POST" action="{{ route('superadmin.tools.content.bulk') }}">
                                                @csrf
                                                <input type="hidden" name="ids[]" value="{{ $post->id }}">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success"><i class="ri-check-line"></i> Sudah saya baca, setujui</button>
                                            </form>
                                        @endif

                                        @if (in_array($post->status, ['approved', 'ready'], true))
                                            <form method="POST" action="{{ route('superadmin.tools.content.media', $post) }}" onsubmit="return confirm('{{ $files ? 'Buat ulang media? Media lama diganti setelah yang baru jadi.' : 'Buat media sekarang?' }} Ada biaya Classai.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(in_array($post->media_status, ['queued', 'processing'], true) || ! $mediaReady)>
                                                    <i class="ri-image-ai-line"></i> {{ $files ? 'Buat ulang media' : 'Buat media' }}</button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#upload-{{ $post->id }}"><i class="ri-upload-2-line"></i> Unggah media sendiri</button>
                                        @endif

                                        @if ($post->status === 'ready')
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="collapse" data-bs-target="#schedule-{{ $post->id }}"><i class="ri-calendar-check-line"></i> Jadwalkan</button>
                                        @endif

                                        @if ($post->status === 'scheduled')
                                            <form method="POST" action="{{ route('superadmin.tools.content.unschedule', $post) }}" onsubmit="return confirm('Batalkan jadwal konten ini?')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Batalkan jadwal</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @if ($files)
                                    <div class="flex-shrink-0 text-center">
                                        @if ($post->isVideo())
                                            <video class="content-thumb" src="{{ route('superadmin.tools.content.file', [$post, 0]) }}" controls preload="metadata" playsinline></video>
                                        @else
                                            <a href="{{ route('superadmin.tools.content.file', [$post, 0]) }}" target="_blank" rel="noopener"><img class="content-thumb" src="{{ route('superadmin.tools.content.file', [$post, 0]) }}" alt="Pratinjau {{ $post->title }}" loading="lazy"></a>
                                        @endif
                                        @if (count($files) > 1) <div class="small text-muted">{{ count($files) }} slide</div> @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Baca / edit --}}
                            <div class="collapse mt-3" id="detail-{{ $post->id }}">
                                @if (count($files) > 1)
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        @foreach ($files as $i => $file)
                                            <a href="{{ route('superadmin.tools.content.file', [$post, $i]) }}" target="_blank" rel="noopener"><img class="content-thumb" src="{{ route('superadmin.tools.content.file', [$post, $i]) }}" alt="Slide {{ $i + 1 }}" loading="lazy"></a>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($post->isEditable())
                                    <form method="POST" action="{{ route('superadmin.tools.content.update', $post) }}" class="row g-2">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small text-muted mb-1">Tanggal tayang</label>
                                            <input type="date" name="publish_date" class="form-control form-control-sm" required value="{{ $post->publish_date->toDateString() }}">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small text-muted mb-1">Jam</label>
                                            <input type="time" name="publish_time" class="form-control form-control-sm" required value="{{ $post->publish_time }}">
                                        </div>
                                        <div class="col-12 col-md-7">
                                            <label class="form-label small text-muted mb-1">Judul (internal)</label>
                                            <input type="text" name="title" class="form-control form-control-sm" maxlength="200" required value="{{ $post->title }}">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1">Hook</label>
                                            <input type="text" name="hook" class="form-control form-control-sm" maxlength="300" value="{{ $post->hook }}">
                                        </div>
                                        <div class="col-12 {{ $post->format === 'carousel' ? 'col-md-6' : '' }}">
                                            <label class="form-label small text-muted mb-1">{{ $post->isVideo() ? 'Naskah video' : ($post->format === 'carousel' ? 'Catatan' : 'Teks di gambar') }}</label>
                                            <textarea name="script" rows="{{ $post->isVideo() ? 7 : 3 }}" class="form-control form-control-sm" maxlength="4000">{{ $post->script }}</textarea>
                                        </div>
                                        @if ($post->format === 'carousel')
                                            <div class="col-12 col-md-6">
                                                <label class="form-label small text-muted mb-1">Slide (satu baris: <code>Judul | isi</code>)</label>
                                                <textarea name="slides" rows="7" class="form-control form-control-sm" maxlength="5000">{{ $post->slidesText() }}</textarea>
                                            </div>
                                        @endif
                                        <div class="col-12 col-md-8">
                                            <label class="form-label small text-muted mb-1">Caption</label>
                                            <textarea name="caption" rows="6" class="form-control form-control-sm" maxlength="2000">{{ $post->caption }}</textarea>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small text-muted mb-1">Hashtag</label>
                                            <textarea name="hashtags" rows="2" class="form-control form-control-sm" maxlength="1000">{{ $post->hashtags }}</textarea>
                                            <label class="form-label small text-muted mb-1 mt-2">Deskripsi visual untuk AI (Inggris)</label>
                                            <textarea name="visual_prompt" rows="3" class="form-control form-control-sm" maxlength="1500">{{ $post->visual_prompt }}</textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-sm btn-primary"><i class="ri-save-line"></i> Simpan perubahan</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('superadmin.tools.content.regenerate', $post) }}" class="d-flex flex-wrap gap-2 mt-2" onsubmit="this.querySelector('button').disabled = true;">
                                        @csrf
                                        <input type="text" name="instruction" class="form-control form-control-sm" style="max-width: 420px" maxlength="500" placeholder="Arahan untuk AI (opsional), mis. lebih lucu, fokus ke pengingat WA">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ri-refresh-line"></i> Tulis ulang dengan AI</button>
                                    </form>
                                @else
                                    <div class="row g-2">
                                        @if ($post->script) <div class="col-12 col-md-6"><div class="small text-muted mb-1">Naskah / teks</div><div class="content-text">{{ $post->script }}</div></div> @endif
                                        @if ($post->slides) <div class="col-12 col-md-6"><div class="small text-muted mb-1">Slide</div><div class="content-text">{{ $post->slidesText() }}</div></div> @endif
                                        <div class="col-12"><div class="small text-muted mb-1">Caption</div><div class="content-text">{{ $post->fullCaption() }}</div></div>
                                    </div>
                                @endif
                            </div>

                            {{-- Unggah media sendiri --}}
                            @if (in_array($post->status, ['approved', 'ready'], true))
                                <div class="collapse mt-3" id="upload-{{ $post->id }}">
                                    <form method="POST" action="{{ route('superadmin.tools.content.upload', $post) }}" enctype="multipart/form-data" class="border rounded p-2 bg-light-subtle d-flex flex-wrap gap-2 align-items-center">
                                        @csrf
                                        <input type="file" name="files[]" class="form-control form-control-sm" style="max-width: 420px" required
                                            @if ($post->isVideo()) accept="video/mp4,video/quicktime" @else accept="image/jpeg,image/png,image/webp" @if ($post->format === 'carousel') multiple @endif @endif>
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="this.disabled = true; this.form.submit();">Unggah</button>
                                        <span class="small text-muted">{{ $post->isVideo() ? '1 video MP4/MOV 9:16, 3–90 detik, maks 300 MB.' : ($post->format === 'carousel' ? '2–10 gambar (urut sesuai nama file dipilih), maks 8 MB per gambar.' : '1 gambar, maks 8 MB.') }}</span>
                                    </form>
                                </div>
                            @endif

                            {{-- Jadwalkan ke akun tertentu --}}
                            @if ($post->status === 'ready')
                                <div class="collapse mt-3" id="schedule-{{ $post->id }}">
                                    <form method="POST" action="{{ route('superadmin.tools.content.schedule', $post) }}" class="border rounded p-3 bg-light-subtle">
                                        @csrf
                                        <div class="small mb-2">Tayang <b>{{ $post->plannedAt()->isPast() ? 'segera (jam tayang sudah lewat)' : $post->plannedAt()->translatedFormat('l, d M Y H:i').' WIB' }}</b>. Ubah tanggal/jam lewat Baca / edit.</div>
                                        @forelse ($allowed as $account)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="accounts[]" value="{{ $account->id }}" id="acc-{{ $post->id }}-{{ $account->id }}" @checked($account->is_default)>
                                                <label class="form-check-label" for="acc-{{ $post->id }}-{{ $account->id }}">
                                                    <i class="{{ $account->platformIcon() }}" style="color: {{ $account->platformColor() }}"></i> {{ $account->platformLabel() }} · {{ $account->displayName() }}</label>
                                            </div>
                                        @empty
                                            <div class="small text-muted">Belum ada akun tersambung yang menerima format {{ $post->formatLabel() }}.</div>
                                        @endforelse

                                        @if ($allowed->contains('platform', 'tiktok'))
                                            @php $options = $allowed->where('platform', 'tiktok')->flatMap(fn ($a) => $a->tiktokPrivacyOptions())->unique(); @endphp
                                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
                                                <span><i class="ri-tiktok-line"></i> Privasi TikTok</span>
                                                <select name="tiktok_privacy" class="form-select form-select-sm w-auto">
                                                    <option value="">Pilih…</option>
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}">{{ $privacyLabels[$option] ?? $option }}</option>
                                                    @endforeach
                                                </select>
                                                <label><input type="checkbox" class="form-check-input" name="tiktok_comment" value="1"> Izinkan komentar</label>
                                                @if ($post->isVideo())
                                                    <label><input type="checkbox" class="form-check-input" name="tiktok_duet" value="1"> Duet</label>
                                                    <label><input type="checkbox" class="form-check-input" name="tiktok_stitch" value="1"> Stitch</label>
                                                @endif
                                            </div>
                                            <div class="small text-muted mt-1">Dengan memposting ke TikTok, Anda menyetujui
                                                <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener noreferrer">Music Usage Confirmation</a> TikTok.</div>
                                        @endif

                                        <button type="submit" class="btn btn-sm btn-warning mt-2" @disabled($allowed->isEmpty())><i class="ri-calendar-check-line"></i> Jadwalkan</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @empty
                    <div class="text-center text-muted py-5">
                        Belum ada konten di rentang ini. <a href="{{ route('superadmin.tools.content.settings') }}">Buat rencana konten</a> dulu.
                    </div>
                @endforelse

                <div class="mt-3">{{ $posts->links() }}</div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('bulk-form');
            const checks = () => Array.from(document.querySelectorAll('.post-check'));
            const pageBox = document.getElementById('check-page');

            const refresh = () => {
                if (! form) return;
                const all = form.elements.all.value === '1';
                const n = checks().filter(c => c.checked).length;
                const info = form.querySelector('[data-selected-info]');
                const selectAll = form.querySelector('[data-select-all]');
                const selectNone = form.querySelector('[data-select-none]');
                info.textContent = all ? 'Semua ' + form.dataset.total + ' konten sesuai filter dipilih' : n + ' dipilih';
                if (selectAll) selectAll.classList.toggle('d-none', all || n === 0 || n < checks().length);
                if (selectNone) selectNone.classList.toggle('d-none', ! all);
                checks().forEach(c => c.closest('.content-item')?.classList.toggle('is-checked', c.checked));
            };

            pageBox?.addEventListener('change', () => {
                checks().forEach(c => c.checked = pageBox.checked);
                if (! pageBox.checked) form.elements.all.value = '0';
                refresh();
            });
            document.addEventListener('change', e => {
                if (e.target.classList?.contains('post-check')) {
                    if (! e.target.checked) { form.elements.all.value = '0'; pageBox.checked = false; }
                    refresh();
                }
            });
            form?.querySelector('[data-select-all]')?.addEventListener('click', () => { form.elements.all.value = '1'; refresh(); });
            form?.querySelector('[data-select-none]')?.addEventListener('click', () => {
                form.elements.all.value = '0'; pageBox.checked = false; checks().forEach(c => c.checked = false); refresh();
            });

            window.contentBulkSubmit = function (f, e) {
                const all = f.elements.all.value === '1';
                const n = all ? Number(f.dataset.total) : checks().filter(c => c.checked).length;
                if (! n) { alert('Centang dulu konten yang mau diproses.'); return false; }
                const button = e.submitter;
                const message = (button?.dataset.confirm || 'Proses konten terpilih?') + '\n\nJumlah: ' + n + ' konten.';
                if (! confirm(message)) return false;
                if (button?.value === 'schedule' && f.elements.tiktok_privacy && ! f.elements.tiktok_privacy.value) {
                    if (! confirm('Privasi TikTok belum dipilih: konten tidak akan dijadwalkan ke TikTok (akun lain tetap). Lanjutkan?')) return false;
                }
                return true;
            };

            // Progres "Buat rencana": cek tiap 8 detik, muat ulang halaman saat selesai.
            const progress = document.getElementById('plan-progress');
            if (progress) {
                const tick = () => fetch(progress.dataset.url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : null)
                    .then(s => {
                        if (! s) return;
                        progress.querySelector('[data-plan-done]').textContent = s.done;
                        if (! s.running) { location.reload(); }
                    }).catch(() => {});
                setInterval(tick, 8000);
            }
            refresh();
        })();
    </script>
@endpush
