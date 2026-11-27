@extends('layouts.dashboard')

@section('title', 'Akun Sosmed')
@section('page-section', 'Tools')

@php
    use App\Models\SocialAccount;
    use App\Models\SocialApp;

    $host = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
    $privacyLabels = ['PUBLIC_TO_EVERYONE' => 'Publik', 'MUTUAL_FOLLOW_FRIENDS' => 'Teman', 'FOLLOWER_OF_CREATOR' => 'Pengikut', 'SELF_ONLY' => 'Hanya saya'];
    $steps = [
        'meta' => [
            'Buka <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener noreferrer">developers.facebook.com › My Apps</a>, buat app tipe <b>Business</b>.',
            'Tambahkan use case <b>Kelola semua hal di Page</b> dan <b>Instagram API (Facebook Login)</b>, lalu produk <b>Facebook Login for Business</b>.',
            'Di Facebook Login › Settings, isi <b>Valid OAuth Redirect URIs</b> dengan Callback URL di samping.',
            'Akun Instagram harus <b>Professional (Business/Creator)</b> dan terhubung ke Facebook Page bizbos.',
            'Untuk akun milik sendiri, app mode <b>Development</b> cukup selama akun Facebook Anda admin app & admin Page. App Review hanya perlu kalau dipakai akun orang lain.',
            'Isi App ID & App Secret (App settings › Basic) di bawah, simpan, lalu klik <b>Sambungkan</b> dan centang Page + akun Instagram-nya.',
        ],
        'tiktok' => [
            'Buka <a href="https://developers.tiktok.com/apps" target="_blank" rel="noopener noreferrer">developers.tiktok.com › Manage apps</a>, buat app (platform Web).',
            'Tambahkan produk <b>Login Kit</b> (Redirect URI = Callback URL di samping) dan <b>Content Posting API</b> (aktifkan <b>Direct Post</b>).',
            'Untuk posting foto/carousel, verifikasi domain <b>'.e($host).'</b> di <b>URL properties</b> (TikTok mengambil foto dari link bizbos).',
            'Sebelum app lolos audit TikTok: hanya bisa posting dengan privasi <b>Hanya saya</b> ke akun yang diset privat (maks 5 akun/24 jam). Ajukan audit agar bisa publik.',
            'Isi Client Key & Client Secret di bawah, simpan, lalu <b>Sambungkan</b>.',
        ],
        'google' => [
            'Buka <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a>, buat project, aktifkan <b>YouTube Data API v3</b>.',
            'Atur <b>OAuth consent screen</b> (External). Selama status <b>Testing</b>, tambahkan email Anda sebagai test user; izin habis tiap 7 hari, jadi setelah siap klik <b>Publish app</b>.',
            'Credentials › Create <b>OAuth client ID</b> (Web application), isi <b>Authorized redirect URIs</b> dengan Callback URL di samping.',
            'Catatan Google: project yang belum lolos audit YouTube API membuat video terunggah terkunci <b>Private</b>. Ajukan audit lewat formulir YouTube API Services.',
            'Isi Client ID & Client Secret di bawah, simpan, lalu <b>Sambungkan</b> dengan akun Google pemilik channel.',
        ],
    ];
@endphp

@section('content')
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link" href="{{ route('superadmin.tools.content.index') }}"><i class="ri-calendar-schedule-line me-1"></i>Konten</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('superadmin.tools.content.settings') }}"><i class="ri-robot-2-line me-1"></i>Buat Rencana & Pengaturan</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('superadmin.tools.social.index') }}"><i class="ri-share-line me-1"></i>Akun Sosmed</a></li>
    </ul>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="ri-error-warning-line"></i> {{ $errors->first() }}</div>
    @endif

    <div class="alert alert-light border small">
        Sambungkan akun resmi bizbos supaya konten yang dijadwalkan di Konten Harian diposting otomatis. Token disimpan terenkripsi di server dan tidak pernah ditampilkan.
        Platform mengambil file media dari <b>{{ $host }}</b>, jadi posting otomatis hanya jalan di server yang bisa dibuka publik (bukan localhost).
    </div>

    @foreach (SocialApp::PROVIDERS as $provider => [$label, $idLabel, $secretLabel, $platforms])
        @php
            $app = $apps[$provider] ?? null;
            $list = collect($platforms)->flatMap(fn ($p) => $accounts[$p] ?? collect());
        @endphp
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h6 class="mb-0">
                        @foreach ($platforms as $platform)
                            <i class="{{ SocialAccount::PLATFORMS[$platform][1] }}" style="color: {{ SocialAccount::PLATFORMS[$platform][2] }}"></i>
                        @endforeach
                        {{ $label }}
                    </h6>
                    <span class="badge {{ $list->isNotEmpty() ? 'bg-success-subtle text-success' : 'bg-light text-dark' }}">{{ $list->where('status', SocialAccount::STATUS_CONNECTED)->count() }} akun tersambung</span>
                    <form method="POST" action="{{ route('superadmin.tools.social.connect', $provider) }}" class="ms-auto">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary" @disabled(! $app)><i class="ri-link"></i> {{ $list->isNotEmpty() ? 'Sambungkan ulang / tambah' : 'Sambungkan' }}</button>
                    </form>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <form method="POST" action="{{ route('superadmin.tools.social.apps.save', $provider) }}" class="row g-2">
                            @csrf
                            @method('PUT')
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">{{ $idLabel }}</label>
                                <input type="text" name="client_id" class="form-control form-control-sm" maxlength="200" required value="{{ $app?->client_id }}" autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">{{ $secretLabel }}</label>
                                <input type="password" name="client_secret" class="form-control form-control-sm" maxlength="255" autocomplete="new-password"
                                    placeholder="{{ $app ? $app->maskedSecret().' (kosongkan bila tidak diganti)' : '' }}" @required(! $app)>
                            </div>
                            @if ($provider === 'meta')
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">Versi Graph API</label>
                                    <input type="text" name="graph_version" class="form-control form-control-sm" maxlength="6" pattern="v\d{2}\.\d" value="{{ $app?->graphVersion() ?? SocialApp::DEFAULT_GRAPH_VERSION }}">
                                </div>
                            @endif
                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Callback / Redirect URL</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" readonly value="{{ SocialApp::callbackUrl($provider) }}" id="cb-{{ $provider }}">
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard?.writeText(document.getElementById('cb-{{ $provider }}').value); this.innerHTML = '<i class=&quot;ri-check-line&quot;></i>';"><i class="ri-file-copy-line"></i></button>
                                </div>
                            </div>
                            <div class="col-12 small text-muted">Izin yang diminta: <code>{{ implode(', ', $scopes[$provider]) }}</code></div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ri-save-line"></i> Simpan kredensial</button>
                            </div>
                        </form>
                        <details class="mt-3 small">
                            <summary class="text-primary">Cara menyiapkan app {{ $label }}</summary>
                            <ol class="mt-2 ps-3 mb-0">
                                @foreach ($steps[$provider] as $step)
                                    <li class="mb-1">{!! $step !!}</li>
                                @endforeach
                            </ol>
                        </details>
                    </div>

                    <div class="col-12 col-lg-7">
                        @forelse ($list as $account)
                            <div class="d-flex flex-wrap align-items-center gap-2 border rounded p-2 mb-2">
                                @if ($account->avatar_url)
                                    <img src="{{ $account->avatar_url }}" alt="" width="36" height="36" class="rounded-circle" referrerpolicy="no-referrer" loading="lazy">
                                @else
                                    <i class="{{ $account->platformIcon() }} fs-4" style="color: {{ $account->platformColor() }}"></i>
                                @endif
                                <div class="flex-grow-1" style="min-width: 180px">
                                    <div class="fw-semibold">{{ $account->displayName() }}</div>
                                    <div class="small text-muted">
                                        {{ $account->platformLabel() }}
                                        @if ($account->platform === 'instagram' && ! empty($account->meta['page_name'])) · lewat Page {{ $account->meta['page_name'] }} @endif
                                        @if ($account->token_expires_at) · token s/d {{ $account->token_expires_at->translatedFormat('d M H:i') }} (diperpanjang otomatis) @endif
                                        @if ($account->platform === 'tiktok')
                                            · privasi: {{ collect($account->tiktokPrivacyOptions())->map(fn ($o) => $privacyLabels[$o] ?? $o)->implode(', ') ?: '-' }}
                                        @endif
                                        @if ($account->active_count) · {{ $account->active_count }} kiriman terjadwal @endif
                                    </div>
                                    @if (! $account->isConnected())
                                        <div class="small text-danger"><i class="ri-error-warning-line"></i> Perlu disambungkan ulang. {{ $account->status_reason }}</div>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('superadmin.tools.social.update', $account) }}" class="form-check form-switch mb-0">
                                    @csrf
                                    @method('PUT')
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_default" value="1" id="def-{{ $account->id }}" @checked($account->is_default) onchange="this.form.submit()">
                                    <label class="form-check-label small" for="def-{{ $account->id }}">Tujuan bawaan</label>
                                </form>
                                <form method="POST" action="{{ route('superadmin.tools.social.refresh', $account) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cek token & perbarui data akun"><i class="ri-refresh-line"></i></button>
                                </form>
                                <form method="POST" action="{{ route('superadmin.tools.social.destroy', $account) }}" onsubmit="return confirm('Putuskan {{ e(addslashes($account->displayName())) }} dari bizbos?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Putuskan"><i class="ri-link-unlink"></i></button>
                                </form>
                            </div>
                        @empty
                            <div class="text-muted small py-3">Belum ada akun. Isi kredensial app, simpan, lalu klik Sambungkan.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
