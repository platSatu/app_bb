@extends('layouts.dashboard')

@section('title', 'Email Blast')
@section('page-section', 'Tools')

@push('styles')
    <style>
        .mail-table { font-size: .875rem; }
        .mail-table .unread td { font-weight: 600; }
        .mail-table .sub { display: block; font-size: .78rem; color: var(--bs-secondary-color, #6c757d); font-weight: normal; }
        .mail-frame { width: 100%; min-height: 480px; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: .375rem; background: #fff; }
        .mail-text { white-space: pre-wrap; font-family: inherit; }
    </style>
@endpush

@section('content')
    @php
        $route = fn ($tab, $extra = []) => route('superadmin.tools.email-blast.index', ['tab' => $tab] + $extra);
    @endphp

    @unless ($setting->isConfigured())
        <div class="alert alert-warning"><i class="ri-mail-settings-line me-1"></i>Akun email pengirim belum diatur. Isi dulu di tab <a href="{{ $route('settings') }}">Pengaturan</a>.</div>
    @endunless

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills mb-3 flex-wrap">
                @foreach (['inbox' => ['ri-inbox-line', 'Inbox'], 'sent' => ['ri-send-plane-line', 'Terkirim'], 'compose' => ['ri-edit-line', 'Email Baru'], 'settings' => ['ri-settings-3-line', 'Pengaturan']] as $key => [$icon, $label])
                    <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ $route($key) }}"><i class="{{ $icon }} me-1"></i>{{ $label }}</a></li>
                @endforeach
            </ul>

            {{-- ===================== INBOX ===================== --}}
            @if ($tab === 'inbox')
                @isset($message)
                    <a href="{{ $route('inbox') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="ri-arrow-left-line"></i> Kembali ke Inbox</a>
                    <h5 class="mb-1">{{ $message['subject'] }}</h5>
                    <div class="small text-muted mb-3">
                        Dari <strong>{{ $message['from'] }}</strong> · ke {{ $message['to'] ?: '-' }}
                        · {{ $message['date']?->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }}
                        @if ($message['attachments']) <br><i class="ri-attachment-2"></i> {{ implode(', ', $message['attachments']) }} (lampiran dibuka lewat webmail) @endif
                    </div>
                    @if ($message['html'])
                        {{-- Isi HTML dari luar ditampilkan dalam iframe ter-sandbox: script & form di dalam email tidak bisa jalan. --}}
                        <iframe class="mail-frame" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="{{ '<base target="_blank">'.$message['html'] }}"></iframe>
                    @else
                        <pre class="mail-text border rounded p-3">{{ $message['text'] }}</pre>
                    @endif
                    @if (filter_var(\Illuminate\Support\Str::between($message['from'], '<', '>'), FILTER_VALIDATE_EMAIL))
                        <a href="{{ $route('compose', ['to' => \Illuminate\Support\Str::between($message['from'], '<', '>'), 'subject' => 'Re: '.$message['subject']]) }}" class="btn btn-primary mt-3"><i class="ri-reply-line"></i> Balas</a>
                    @endif
                @elseif (! empty($inboxError))
                    <div class="alert alert-danger mb-0">{{ $inboxError }}</div>
                @elseif (isset($inbox))
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 mail-table">
                            <thead class="table-light"><tr><th>Dari</th><th>Subjek</th><th class="text-end">Tanggal</th></tr></thead>
                            <tbody>
                                @forelse ($inbox['messages'] as $m)
                                    <tr @class(['unread' => ! $m['seen']]) style="cursor: pointer" onclick="location.href='{{ route('superadmin.tools.email-blast.message', $m['uid']) }}'">
                                        <td class="text-truncate" style="max-width: 260px">{{ $m['from'] }}</td>
                                        <td>{{ $m['subject'] }}</td>
                                        <td class="text-end text-nowrap">{{ $m['date']?->timezone(config('app.timezone'))->translatedFormat('d M H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">Inbox kosong.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @php $lastPage = max(1, (int) ceil($inbox['total'] / \App\Services\Marketing\EmailBlastService::INBOX_PER_PAGE)); @endphp
                    <div class="d-flex justify-content-between align-items-center mt-3 small text-muted">
                        <span>{{ number_format($inbox['total'], 0, ',', '.') }} email · halaman {{ $inbox['page'] }} dari {{ $lastPage }}</span>
                        <span class="d-flex gap-2">
                            @if ($inbox['page'] > 1) <a class="btn btn-sm btn-outline-secondary" href="{{ $route('inbox', ['page' => $inbox['page'] - 1]) }}"><i class="ri-arrow-left-s-line"></i> Lebih baru</a> @endif
                            @if ($inbox['page'] < $lastPage) <a class="btn btn-sm btn-outline-secondary" href="{{ $route('inbox', ['page' => $inbox['page'] + 1]) }}">Lebih lama <i class="ri-arrow-right-s-line"></i></a> @endif
                        </span>
                    </div>
                @endisset
            @endif

            {{-- ===================== TERKIRIM ===================== --}}
            @if ($tab === 'sent')
                @isset($blast)
                    <a href="{{ $route('sent') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="ri-arrow-left-line"></i> Kembali ke Terkirim</a>
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <h5 class="mb-1">{{ $blast->subject }}</h5>
                            <div class="small text-muted">{{ $blast->created_at->translatedFormat('d M Y H:i') }} · {{ count($blast->recipients) }} penerima</div>
                        </div>
                        <div class="text-end">
                            <span class="badge {{ $blast->statusBadge() }}">{{ $blast->statusLabel() }}</span>
                            <div class="small mt-1"><span class="text-success">{{ $blast->sent_count }} terkirim</span> · <span class="text-danger">{{ $blast->failed_count }} gagal</span></div>
                            @if ($blast->status !== 'done' && $blast->updated_at->lt(now()->subMinutes(5)))
                                <form method="POST" action="{{ route('superadmin.tools.email-blast.resume', $blast) }}" class="mt-2">
                                    @csrf
                                    <button class="btn btn-sm btn-warning"><i class="ri-play-line"></i> Lanjutkan kiriman</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <pre class="mail-text border rounded p-3 mb-3">{{ $blast->body }}</pre>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 mail-table">
                            <thead class="table-light"><tr><th>Penerima</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($blast->recipients as $email)
                                    @php $result = $blast->results[$email] ?? null; @endphp
                                    <tr>
                                        <td>{{ $email }}</td>
                                        <td>
                                            @if ($result === 'sent') <span class="text-success"><i class="ri-check-line"></i> Terkirim</span>
                                            @elseif ($result) <span class="text-danger"><i class="ri-close-line"></i> Gagal</span><span class="sub">{{ $result }}</span>
                                            @else <span class="text-muted">Menunggu</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($blast->status !== 'done') <script>setTimeout(() => location.reload(), 8000);</script> @endif
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 mail-table">
                            <thead class="table-light"><tr><th>Subjek</th><th>Penerima</th><th>Hasil</th><th class="text-end">Tanggal</th></tr></thead>
                            <tbody>
                                @forelse ($blasts as $b)
                                    <tr style="cursor: pointer" onclick="location.href='{{ route('superadmin.tools.email-blast.show', $b) }}'">
                                        <td>{{ $b->subject }}<span class="sub">oleh {{ $b->creator?->name ?? '-' }}</span></td>
                                        <td>{{ count($b->recipients) }}</td>
                                        <td><span class="badge {{ $b->statusBadge() }}">{{ $b->statusLabel() }}</span> <span class="small text-success ms-1">{{ $b->sent_count }}</span>@if ($b->failed_count) <span class="small text-danger">/ {{ $b->failed_count }} gagal</span>@endif</td>
                                        <td class="text-end text-nowrap">{{ $b->created_at->translatedFormat('d M Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada email terkirim.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $blasts->links() }}</div>
                @endisset
            @endif

            {{-- ===================== EMAIL BARU ===================== --}}
            @if ($tab === 'compose')
                <form method="POST" action="{{ route('superadmin.tools.email-blast.store') }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Dari</label>
                        <input type="text" class="form-control" value="{{ $setting->from_name ? $setting->from_name.' <'.$setting->from_address.'>' : ($setting->from_address ?? '-') }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kepada</label>
                        <textarea name="recipients" rows="4" class="form-control font-monospace small" required maxlength="20000"
                                  placeholder="satu@contoh.com, dua@contoh.com&#10;atau satu alamat per baris">{{ old('recipients', request('to')) }}</textarea>
                        <div class="form-text">
                            Tempel daftar email (pisah koma / baris baru). Tiap penerima dikirimi email sendiri, alamat penerima lain tidak terlihat.
                            Maks {{ \App\Services\Marketing\EmailBlastService::MAX_RECIPIENTS }} per kiriman.
                            Hari ini: <strong>{{ $usedToday }}</strong> / {{ $setting->daily_limit }}.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subjek</label>
                        <input type="text" name="subject" class="form-control" required maxlength="200" value="{{ old('subject', request('subject')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Isi email</label>
                        <textarea name="body" rows="12" class="form-control" required maxlength="20000">{{ old('body') }}</textarea>
                        <div class="form-text">Teks biasa; baris baru ikut terkirim. Link (https://...) tetap bisa diklik di kebanyakan aplikasi email.</div>
                    </div>
                    <button type="submit" class="btn btn-primary" @disabled(! $setting->isConfigured())><i class="ri-send-plane-line"></i> Kirim</button>
                </form>
            @endif

            {{-- ===================== PENGATURAN ===================== --}}
            @if ($tab === 'settings')
                <form method="POST" action="{{ route('superadmin.tools.email-blast.settings') }}" class="row g-3" style="max-width: 760px" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
                    @csrf
                    @method('PUT')
                    <div class="col-md-6">
                        <label class="form-label">Nama pengirim</label>
                        <input type="text" name="from_name" class="form-control" maxlength="100" value="{{ old('from_name', $setting->from_name) }}" placeholder="mis. Tim bizbos">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alamat email pengirim</label>
                        <input type="email" name="from_address" class="form-control" required maxlength="150" value="{{ old('from_address', $setting->from_address) }}" placeholder="mis. halo@bizbos.id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Server (SMTP & IMAP)</label>
                        <input type="text" name="host" class="form-control" required maxlength="150" value="{{ old('host', $setting->host) }}" placeholder="mis. mail.bizbos.id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required maxlength="150" value="{{ old('username', $setting->username) }}" placeholder="biasanya sama dengan alamat email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" maxlength="300" autocomplete="new-password"
                               placeholder="{{ $setting->password ? 'Sudah tersimpan, isi untuk mengganti' : 'Belum diisi' }}">
                        <div class="form-text">Disimpan terenkripsi.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Enkripsi</label>
                        <select name="encryption" class="form-select">
                            <option value="ssl" @selected(old('encryption', $setting->encryption) === 'ssl')>SSL (SMTP 465, IMAP 993)</option>
                            <option value="tls" @selected(old('encryption', $setting->encryption) === 'tls')>STARTTLS (SMTP 587, IMAP 143)</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Port SMTP</label>
                        <input type="number" name="smtp_port" class="form-control" required min="1" max="65535" value="{{ old('smtp_port', $setting->smtp_port) }}">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Port IMAP</label>
                        <input type="number" name="imap_port" class="form-control" required min="1" max="65535" value="{{ old('imap_port', $setting->imap_port) }}">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Batas per hari</label>
                        <input type="number" name="daily_limit" class="form-control" required min="1" max="2000" value="{{ old('daily_limit', $setting->daily_limit) }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Cek koneksi & simpan</button>
                        <div class="form-text">Pengaturan dicek dulu ke server email (login SMTP & IMAP); kalau gagal, tidak disimpan. Data server biasanya ada di cPanel › Email Accounts › Connect Devices.</div>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
