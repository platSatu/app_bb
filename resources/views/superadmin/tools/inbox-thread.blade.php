@extends('layouts.dashboard')

@section('title', 'Inbox · '.$thread->displayName())
@section('page-section', 'Tools')

@php
    use App\Models\SocialThread;
    use App\Services\Social\Inbox\SocialInbox;

    $account = $thread->account;
    $limit = $thread->kind === SocialThread::KIND_DM ? SocialInbox::MAX_DM : SocialInbox::MAX_COMMENT;
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('superadmin.tools.inbox.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-line"></i> Inbox</a>
        <h5 class="mb-0">
            <i class="{{ $account?->platformIcon() }}" style="color: {{ $account?->platformColor() }}"></i>
            {{ $thread->displayName() }}
        </h5>
        <span class="badge bg-light text-dark border">{{ $account?->platformLabel() }} · {{ $thread->kindLabel() }}</span>
        @if ($thread->contact_handle && $thread->contact_name)
            <span class="small text-muted">{{ '@'.ltrim($thread->contact_handle, '@') }}</span>
        @endif
        @if ($thread->context_url)
            <a href="{{ $thread->context_url }}" target="_blank" rel="noopener noreferrer" class="small"><i class="ri-external-link-line"></i> Lihat post</a>
        @endif

        <div class="ms-auto d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('superadmin.tools.inbox.update', $thread) }}">
                @csrf
                @method('PUT')
                @if ($thread->handler === 'admin')
                    <input type="hidden" name="handler" value="ai">
                    <button type="submit" class="btn btn-sm btn-outline-info"><i class="ri-robot-2-line"></i> Serahkan ke AI</button>
                @else
                    <input type="hidden" name="handler" value="admin">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="ri-user-line"></i> Pegang (AI berhenti)</button>
                @endif
            </form>
            <form method="POST" action="{{ route('superadmin.tools.inbox.update', $thread) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="{{ $thread->status === 'done' ? 'open' : 'done' }}">
                <button type="submit" class="btn btn-sm {{ $thread->status === 'done' ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    <i class="{{ $thread->status === 'done' ? 'ri-inbox-unarchive-line' : 'ri-check-double-line' }}"></i> {{ $thread->status === 'done' ? 'Buka lagi' : 'Tandai selesai' }}
                </button>
            </form>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="ri-error-warning-line"></i> {{ $errors->first() }}</div>
    @endif

    @if ($thread->needs_human)
        <div class="alert alert-warning py-2 small"><i class="ri-user-voice-line"></i> AI tidak menjawab percakapan ini (topik sensitif, di luar info AI, atau batas AI tercapai). Silakan dijawab admin.</div>
    @endif

    <div class="card mb-3">
        <div class="card-body" style="max-height: 60vh; overflow-y: auto;" id="thread-body">
            @forelse ($messages as $message)
                @php $in = $message->isInbound(); @endphp
                <div class="d-flex mb-3 {{ $in ? '' : 'justify-content-end' }}">
                    <div class="rounded-3 px-3 py-2 {{ $in ? 'bg-light' : ($message->status === 'failed' ? 'bg-danger-subtle' : 'bg-primary-subtle') }}" style="max-width: 75%;">
                        <div class="small fw-semibold mb-1">
                            @if ($in)
                                {{ $message->author_name ?: $thread->displayName() }}
                            @else
                                {{ match ($message->via) { 'ai' => 'AI', 'admin' => $message->sender?->name ?? 'Admin', default => ($account?->name ?? 'bizbos').' (dari aplikasi)' } }}
                                @if ($message->via === 'ai')
                                    <i class="ri-robot-2-line"></i>
                                @endif
                            @endif
                        </div>
                        @if (filled($message->text))
                            <div style="white-space: pre-wrap; word-break: break-word;">{{ $message->text }}</div>
                        @endif
                        @foreach ((array) $message->attachments as $attachment)
                            <div class="small">
                                <i class="ri-attachment-2"></i>
                                @if (! empty($attachment['url']))
                                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer nofollow">Lampiran {{ $attachment['type'] ?? '' }}</a>
                                @else
                                    Lampiran {{ $attachment['type'] ?? '' }}
                                @endif
                            </div>
                        @endforeach
                        @if ($message->status === 'failed')
                            <div class="small text-danger mt-1"><i class="ri-error-warning-line"></i> Gagal terkirim: {{ $message->error }}</div>
                        @endif
                        <div class="small text-muted text-end mt-1">{{ $message->sent_at?->format('d M H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4">Belum ada pesan.</div>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if (! $account?->isConnected())
                <div class="alert alert-warning small mb-0">Akun {{ $account?->platformLabel() }} perlu disambungkan ulang di <a href="{{ route('superadmin.tools.social.index') }}">Tools › Akun Sosmed</a>.</div>
            @elseif (! $thread->canReply())
                <div class="alert alert-secondary small mb-0"><i class="ri-time-line"></i> Sudah lewat 24 jam sejak pesan terakhir orang ini ({{ $thread->windowEndsAt()?->format('d M H:i') }}). Aturan Meta tidak mengizinkan balasan sampai dia mengirim pesan baru.</div>
            @else
                <form method="POST" action="{{ route('superadmin.tools.inbox.reply', $thread) }}" id="reply-form">
                    @csrf
                    <label class="form-label small text-muted mb-1" for="reply-text">
                        {{ $thread->kind === SocialThread::KIND_DM ? 'Balas pesan' : 'Balas komentar (tampil publik)' }}
                        @if ($thread->windowEndsAt())
                            · bisa dibalas sampai {{ $thread->windowEndsAt()->format('d M H:i') }}
                        @endif
                    </label>
                    <textarea name="text" id="reply-text" rows="3" maxlength="{{ $limit }}" required class="form-control">{{ old('text') }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ri-send-plane-line"></i> Kirim</button>
                        @if ($setting->aiEnabled())
                            <button type="button" class="btn btn-sm btn-outline-info" id="ai-draft" data-url="{{ route('superadmin.tools.inbox.draft', $thread) }}"><i class="ri-robot-2-line"></i> Buat draf AI</button>
                        @endif
                        <span class="small text-muted" id="ai-draft-note"></span>
                        <span class="small text-muted ms-auto">Maks {{ $limit }} karakter. Membalas = percakapan dipegang admin (AI berhenti).</span>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const body = document.getElementById('thread-body');
            if (body) body.scrollTop = body.scrollHeight;

            const button = document.getElementById('ai-draft');
            const note = document.getElementById('ai-draft-note');
            const text = document.getElementById('reply-text');
            const form = document.getElementById('reply-form');

            form?.addEventListener('submit', () => form.querySelector('button[type=submit]').disabled = true);

            button?.addEventListener('click', async () => {
                button.disabled = true;
                note.textContent = 'AI sedang menulis draf...';
                try {
                    const response = await fetch(button.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const data = await response.json().catch(() => ({}));
                    if (response.ok && data.text) {
                        text.value = data.text;
                        note.textContent = 'Draf AI siap. Periksa dulu sebelum dikirim.';
                        text.focus();
                    } else {
                        note.textContent = data.message || 'AI belum bisa membuat draf.';
                    }
                } catch (e) {
                    note.textContent = 'Koneksi terputus. Coba lagi.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
@endpush
