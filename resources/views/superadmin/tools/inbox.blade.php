@extends('layouts.dashboard')

@section('title', 'Inbox')
@section('page-section', 'Tools')

@php
    use App\Http\Controllers\Superadmin\Tools\InboxController;
    use App\Models\SocialAccount;
    use App\Models\SocialInboxSetting;

    $webhookUrl = url('/api/webhooks/meta');
    $metaPages = $accounts->where('platform', 'facebook');
    $youtube = $accounts->where('platform', 'youtube');
@endphp

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger"><i class="ri-error-warning-line"></i> {{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Channel">
            @foreach (InboxController::CHANNELS as $key => $label)
                <a href="{{ route('superadmin.tools.inbox.index', ['channel' => $key, 'filter' => $filter, 'q' => $q ?: null]) }}"
                    class="btn {{ $channel === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                    @if ($key === 'whatsapp')
                        <i class="ri-whatsapp-line"></i>
                    @elseif (isset(SocialAccount::PLATFORMS[$key]))
                        <i class="{{ SocialAccount::PLATFORMS[$key][1] }}"></i>
                    @endif
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form method="GET" class="d-flex gap-2 ms-auto">
            <input type="hidden" name="channel" value="{{ $channel }}">
            <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter">
                @foreach (InboxController::FILTERS as $key => $label)
                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}{{ $key === 'human' && $counts['human'] ? ' ('.$counts['human'].')' : '' }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $q }}" maxlength="100" class="form-control form-control-sm" placeholder="Cari nama / pesan">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ri-search-line"></i></button>
        </form>
    </div>

    @if ($counts['human'] > 0 && $filter !== 'human')
        <div class="alert alert-warning py-2 small d-flex align-items-center gap-2">
            <i class="ri-user-voice-line fs-5"></i>
            <div class="flex-grow-1">{{ $counts['human'] }} percakapan butuh dijawab admin (komplain, pertanyaan di luar info AI, atau batas AI tercapai).</div>
            <a href="{{ route('superadmin.tools.inbox.index', ['channel' => $channel, 'filter' => 'human']) }}" class="btn btn-sm btn-warning">Lihat</a>
        </div>
    @endif

    <div class="card mb-3">
        <div class="list-group list-group-flush">
            @forelse ($rows as $row)
                <a href="{{ $row['url'] }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-start py-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                        style="width: 38px; height: 38px; background: {{ $row['color'] }}1a; color: {{ $row['color'] }};">
                        <i class="{{ $row['icon'] }} fs-5"></i>
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold {{ $row['unread'] ? '' : 'text-body' }}">{{ $row['name'] }}</span>
                            <span class="badge bg-light text-dark border">{{ $row['label'] }} · {{ $row['kind'] }}</span>
                            @if ($row['human'])
                                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="ri-user-voice-line"></i> Butuh admin</span>
                            @elseif ($row['handler'] === 'admin')
                                <span class="badge bg-secondary-subtle text-secondary-emphasis">Dipegang admin</span>
                            @elseif ($row['handler'] === 'ai' && $setting->autoFor($row['channel']))
                                <span class="badge bg-info-subtle text-info-emphasis"><i class="ri-robot-2-line"></i> AI</span>
                            @endif
                            @if ($row['status'] === 'done')
                                <span class="badge bg-success-subtle text-success">Selesai</span>
                            @endif
                            <small class="text-muted ms-auto text-nowrap" title="{{ $row['at']?->format('d M Y H:i') }}">{{ $row['at']?->diffForHumans() }}</small>
                        </div>
                        <div class="small text-muted text-truncate">{{ $row['preview'] ?: '—' }}</div>
                        <div class="small text-muted"><i class="ri-account-circle-line"></i> {{ $row['account'] }}</div>
                    </div>
                    @if ($row['unread'] > 0)
                        <span class="badge rounded-pill bg-danger align-self-center">{{ $row['unread'] }}</span>
                    @endif
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-5">
                    <i class="ri-inbox-line fs-1 d-block mb-2"></i>
                    Belum ada percakapan untuk filter ini.
                </div>
            @endforelse
        </div>
        @if ($rows->count() >= 100)
            <div class="card-footer small text-muted">Menampilkan 100 percakapan terbaru. Pakai pencarian untuk yang lebih lama.</div>
        @endif
    </div>

    <div class="small text-muted mb-3">
        <i class="ri-information-line"></i>
        Chat WhatsApp {{ $hasWhatsapp ? 'dari nomor branch superadmin dibuka & dibalas di Device & Inbox WhatsApp (bot WhatsApp yang sudah ada tetap berjalan seperti biasa).' : 'tampil di sini setelah superadmin punya branch dengan paket WhatsApp aktif (php artisan whatsapp:superadmin-branch).' }}
    </div>

    {{-- Pengaturan AI --}}
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3"><i class="ri-robot-2-line"></i> Agent AI Inbox</h6>
            <form method="POST" action="{{ route('superadmin.tools.inbox.settings') }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-12 col-lg-4">
                    <label class="form-label small text-muted mb-1">Mode AI</label>
                    @foreach (SocialInboxSetting::AI_MODES as $mode => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ai_mode" id="ai-{{ $mode }}" value="{{ $mode }}" @checked(old('ai_mode', $setting->ai_mode) === $mode)>
                            <label class="form-check-label small" for="ai-{{ $mode }}">{{ $label }}</label>
                        </div>
                    @endforeach

                    <label class="form-label small text-muted mb-1 mt-3">Platform yang dibalas AI otomatis</label>
                    @foreach (SocialInboxSetting::AI_PLATFORMS as $platform)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ai_platforms[]" id="aip-{{ $platform }}" value="{{ $platform }}" @checked(in_array($platform, old('ai_platforms', (array) $setting->ai_platforms), true))>
                            <label class="form-check-label small" for="aip-{{ $platform }}"><i class="{{ SocialAccount::PLATFORMS[$platform][1] }}" style="color: {{ SocialAccount::PLATFORMS[$platform][2] }}"></i> {{ SocialAccount::PLATFORMS[$platform][0] }}</label>
                        </div>
                    @endforeach

                    <label class="form-label small text-muted mb-1 mt-3" for="ai-max">Batas balasan AI per jam</label>
                    <input type="number" name="ai_max_per_hour" id="ai-max" min="1" max="200" class="form-control form-control-sm" style="max-width: 120px" value="{{ old('ai_max_per_hour', $setting->ai_max_per_hour) }}" required>
                </div>
                <div class="col-12 col-lg-8">
                    <label class="form-label small text-muted mb-1" for="ai-info">Info untuk AI (AI hanya menjawab berdasarkan teks ini)</label>
                    <textarea name="ai_instructions" id="ai-info" rows="8" maxlength="5000" class="form-control form-control-sm">{{ old('ai_instructions', $setting->ai_instructions) }}</textarea>
                    <ul class="small text-muted mt-2 mb-0 ps-3">
                        <li>AI tidak membalas percakapan yang sedang dipegang admin. Begitu admin membalas, AI berhenti di percakapan itu.</li>
                        <li>Komplain, refund, pembayaran, data pribadi, atau pertanyaan di luar info di atas: AI tidak menjawab, percakapan ditandai <b>Butuh admin</b>.</li>
                        <li>AI hanya membalas pesan baru (maks {{ \App\Services\Social\Inbox\SocialAgent::FRESH_HOURS }} jam). DM Instagram/Facebook hanya bisa dibalas dalam 24 jam (aturan Meta).</li>
                    </ul>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ri-save-line"></i> Simpan pengaturan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Sambungan --}}
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3"><i class="ri-plug-line"></i> Sambungan pesan & komentar</h6>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="fw-semibold small mb-2"><i class="ri-instagram-line" style="color:#E1306C"></i> <i class="ri-facebook-circle-line" style="color:#1877F2"></i> Instagram & Facebook (webhook Meta)</div>
                    <label class="form-label small text-muted mb-1">Callback URL</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control" readonly value="{{ $webhookUrl }}" id="wh-url">
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard?.writeText(document.getElementById('wh-url').value); this.innerHTML = '<i class=&quot;ri-check-line&quot;></i>';"><i class="ri-file-copy-line"></i></button>
                    </div>
                    <label class="form-label small text-muted mb-1">Verify token</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="password" class="form-control" readonly value="{{ $setting->webhook_verify_token }}" id="wh-token" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" onclick="const i = document.getElementById('wh-token'); i.type = i.type === 'password' ? 'text' : 'password';"><i class="ri-eye-line"></i></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard?.writeText(document.getElementById('wh-token').value); this.innerHTML = '<i class=&quot;ri-check-line&quot;></i>';"><i class="ri-file-copy-line"></i></button>
                    </div>

                    @forelse ($metaPages as $page)
                        <div class="d-flex align-items-center gap-2 small border rounded p-2 mb-2">
                            <i class="ri-facebook-circle-line" style="color:#1877F2"></i>
                            <span class="flex-grow-1">{{ $page->name }}
                                @if (! empty($page->meta['inbox_subscribed_at']))
                                    <span class="badge bg-success-subtle text-success">Webhook aktif</span>
                                @endif
                            </span>
                            <form method="POST" action="{{ route('superadmin.tools.inbox.subscribe', $page) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $page->isConnected())>{{ empty($page->meta['inbox_subscribed_at']) ? 'Aktifkan webhook' : 'Aktifkan ulang' }}</button>
                            </form>
                        </div>
                    @empty
                        <div class="small text-muted mb-2">Belum ada Facebook Page. Sambungkan dulu di <a href="{{ route('superadmin.tools.social.index') }}">Tools › Akun Sosmed</a>.</div>
                    @endforelse

                    <details class="small">
                        <summary class="text-primary">Cara menyiapkan di Meta</summary>
                        <ol class="mt-2 ps-3 mb-0">
                            <li class="mb-1">Di Facebook Login for Business › Configurations, tambahkan izin <code>pages_manage_metadata, pages_messaging, pages_read_user_content, pages_manage_engagement, instagram_manage_messages, instagram_manage_comments</code>.</li>
                            <li class="mb-1">Buka <a href="{{ route('superadmin.tools.social.index') }}">Akun Sosmed</a> › Meta › <b>Sambungkan ulang</b> supaya token Page membawa izin baru.</li>
                            <li class="mb-1">Di dashboard app Meta › <b>Webhooks</b>: pilih <b>Page</b>, isi Callback URL & Verify token di atas, centang <code>messages</code>, <code>message_echoes</code>, <code>feed</code>. Pilih <b>Instagram</b>, centang <code>messages</code>, <code>comments</code>.</li>
                            <li class="mb-1">Di Instagram app: Settings › Messages and story replies › Message controls › <b>Connected tools</b>: izinkan akses pesan.</li>
                            <li class="mb-1">Klik <b>Aktifkan webhook</b> di Page di atas.</li>
                            <li>Selama app Meta belum lolos App Review, pesan hanya masuk/terkirim untuk akun yang punya peran di app (admin/developer/tester).</li>
                        </ol>
                    </details>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="fw-semibold small mb-2"><i class="ri-youtube-line" style="color:#FF0000"></i> YouTube (komentar, dicek tiap 10 menit)</div>
                    @forelse ($youtube as $channelAccount)
                        <div class="d-flex flex-wrap align-items-center gap-2 small border rounded p-2 mb-2">
                            <span class="flex-grow-1">{{ $channelAccount->displayName() }}
                                @if (! empty($channelAccount->meta['inbox_polled_at']))
                                    <span class="text-muted">· dicek {{ \Illuminate\Support\Carbon::parse($channelAccount->meta['inbox_polled_at'])->diffForHumans() }}</span>
                                @endif
                            </span>
                            <form method="POST" action="{{ route('superadmin.tools.inbox.sync', $channelAccount) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary" @disabled(! $channelAccount->isConnected())><i class="ri-refresh-line"></i> Ambil sekarang</button>
                            </form>
                            @if (! empty($channelAccount->meta['inbox_error']))
                                <div class="w-100 text-danger">{{ $channelAccount->meta['inbox_error'] }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="small text-muted mb-2">Belum ada channel YouTube tersambung.</div>
                    @endforelse
                    <div class="small text-muted">Untuk membalas komentar, YouTube perlu izin <code>youtube.force-ssl</code>: tambahkan scope itu di Google Cloud › OAuth consent screen › Data access, lalu <b>Sambungkan ulang</b> YouTube di Akun Sosmed.</div>
                    <div class="small text-muted mt-2"><i class="ri-tiktok-line"></i> TikTok belum menyediakan API pesan/komentar untuk app umum, jadi belum bisa masuk Inbox.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
