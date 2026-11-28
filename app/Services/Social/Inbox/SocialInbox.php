<?php

namespace App\Services\Social\Inbox;

use App\Jobs\AutoReplySocial;
use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Models\SocialInboxSetting;
use App\Models\SocialMessage;
use App\Models\SocialThread;
use App\Models\User;
use App\Services\Social\MetaGraph;
use App\Services\Social\SocialException;
use App\Services\Social\SocialManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tools › Inbox untuk akun sosmed resmi bizbos (Tools › Akun Sosmed):
 *  - masuk: webhook Meta (DM & komentar Instagram / Facebook Page) dan polling komentar YouTube;
 *  - keluar: balasan admin atau AI lewat API resmi masing-masing platform.
 * Pesan disimpan sekali saja (unik per percakapan + ID pesan platform), jadi webhook yang dikirim ulang tidak dobel.
 * Tidak menyentuh WhatsApp: WhatsApp di Inbox hanya dibaca dari tabel wa_* oleh controller.
 */
class SocialInbox
{
    private const YOUTUBE_API = 'https://www.googleapis.com/youtube/v3';

    /** Batas panjang teks yang diterima platform (Instagram DM < 1000 karakter). */
    public const MAX_DM = 1000;

    public const MAX_COMMENT = 2000;

    public function __construct(private readonly SocialManager $social) {}

    /* ---------------------------------------------------------------- Meta (webhook) */

    /** Isi webhook Meta yang sudah lolos cek tanda tangan. Event yang tidak dikenal dilewati. */
    public function ingestMeta(array $payload): void
    {
        $object = (string) ($payload['object'] ?? '');
        $platform = match ($object) {
            'page' => 'facebook',
            'instagram' => 'instagram',
            default => null,
        };

        if (! $platform) {
            return;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry) || empty($entry['id'])) {
                continue;
            }

            $account = SocialAccount::where('platform', $platform)->where('external_id', (string) $entry['id'])->first();

            if (! $account) {
                continue; // bukan akun yang tersambung di bizbos
            }

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                if (is_array($event)) {
                    $this->guard(fn () => $this->metaMessage($account, $event));
                }
            }

            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (! is_array($change) || ! is_array($change['value'] ?? null)) {
                    continue;
                }

                $field = (string) ($change['field'] ?? '');

                if ($platform === 'facebook' && $field === 'feed') {
                    $this->guard(fn () => $this->facebookComment($account, $change['value']));
                } elseif ($platform === 'instagram' && $field === 'comments') {
                    $this->guard(fn () => $this->instagramComment($account, $change['value']));
                }
            }
        }
    }

    private function metaMessage(SocialAccount $account, array $event): void
    {
        $message = $event['message'] ?? null;

        if (! is_array($message) || empty($message['mid']) || ! empty($message['is_deleted'])) {
            return; // read receipt, reaction, postback, dll.
        }

        $echo = ! empty($message['is_echo']);
        $contactId = (string) ($echo ? ($event['recipient']['id'] ?? '') : ($event['sender']['id'] ?? ''));

        if ($contactId === '' || $contactId === $account->external_id || $contactId === (string) (($account->meta ?? [])['page_id'] ?? '')) {
            return;
        }

        $thread = $this->thread($account, SocialThread::KIND_DM, $contactId, ['contact_id' => $contactId]);

        if (! $thread->contact_name && ! $echo) {
            $this->fillDmContact($account, $thread);
        }

        $this->record($thread, [
            'external_id' => (string) $message['mid'],
            'direction' => $echo ? 'out' : 'in',
            'author_name' => $echo ? $account->name : $thread->contact_name,
            'text' => isset($message['text']) ? mb_substr((string) $message['text'], 0, 5000) : null,
            'attachments' => self::attachments($message['attachments'] ?? []),
            'via' => 'platform',
            'sent_at' => self::time($event['timestamp'] ?? null),
        ]);
    }

    /** Komentar baru di post Page. Komentar dari Page sendiri dicatat sebagai balasan keluar. */
    private function facebookComment(SocialAccount $account, array $value): void
    {
        if (($value['item'] ?? '') !== 'comment' || ($value['verb'] ?? '') !== 'add' || empty($value['comment_id'])) {
            return;
        }

        $postId = (string) ($value['post_id'] ?? '');
        $parentId = (string) ($value['parent_id'] ?? '');
        // Komentar utama: parent = post. Balasan: parent = komentar utama.
        $rootId = $parentId !== '' && $parentId !== $postId ? $parentId : (string) $value['comment_id'];
        $fromId = (string) ($value['from']['id'] ?? '');
        $own = $fromId === $account->external_id;

        $thread = $this->thread($account, SocialThread::KIND_COMMENT, $rootId, [
            'context_id' => $postId ?: null,
            'context_url' => $postId !== '' ? 'https://www.facebook.com/'.$postId : null,
        ] + ($own ? [] : ['contact_id' => $fromId ?: null, 'contact_name' => self::clip($value['from']['name'] ?? null)]));

        $this->record($thread, [
            'external_id' => (string) $value['comment_id'],
            'direction' => $own ? 'out' : 'in',
            'author_name' => self::clip($value['from']['name'] ?? null),
            'text' => isset($value['message']) ? mb_substr((string) $value['message'], 0, 5000) : null,
            'attachments' => isset($value['photo']) ? [['type' => 'image', 'url' => (string) $value['photo']]] : null,
            'via' => 'platform',
            'sent_at' => self::time($value['created_time'] ?? null),
        ]);
    }

    private function instagramComment(SocialAccount $account, array $value): void
    {
        if (empty($value['id'])) {
            return;
        }

        $fromId = (string) ($value['from']['id'] ?? '');
        $own = $fromId === $account->external_id;
        $rootId = (string) (($value['parent_id'] ?? '') ?: $value['id']);
        $mediaId = (string) ($value['media']['id'] ?? '');

        $thread = $this->thread($account, SocialThread::KIND_COMMENT, $rootId, [
            'context_id' => $mediaId ?: null,
        ] + ($own ? [] : ['contact_id' => $fromId ?: null, 'contact_handle' => self::clip($value['from']['username'] ?? null)]));

        $this->record($thread, [
            'external_id' => (string) $value['id'],
            'direction' => $own ? 'out' : 'in',
            'author_name' => self::clip($value['from']['username'] ?? null),
            'text' => isset($value['text']) ? mb_substr((string) $value['text'], 0, 5000) : null,
            'via' => 'platform',
            'sent_at' => now(),
        ]);
    }

    /** Nama pengirim DM (sekali per percakapan). Gagal = dibiarkan kosong, tidak menghentikan pesan. */
    private function fillDmContact(SocialAccount $account, SocialThread $thread): void
    {
        try {
            $fields = $account->platform === 'instagram' ? 'name,username' : 'first_name,last_name';
            $data = $this->graph()->get($thread->external_id, ['fields' => $fields], (string) $account->access_token, 15);

            $thread->forceFill([
                'contact_name' => self::clip(($data['name'] ?? '') ?: trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''))),
                'contact_handle' => self::clip($data['username'] ?? null),
            ])->save();
        } catch (Throwable) {
            // Izin profil belum ada atau orangnya membatasi: cukup tampil "Pengguna Instagram".
        }
    }

    /**
     * Daftarkan Page ke app supaya Meta mengirim webhook DM & komentar (Page + Instagram yang terhubung).
     * Field webhook Instagram (messages, comments) tetap dicentang di dashboard Meta.
     */
    public function subscribePage(SocialAccount $page): void
    {
        if ($page->platform !== 'facebook') {
            throw new SocialException('Langganan webhook diaktifkan lewat akun Facebook Page.');
        }

        $result = $this->graph()->post($page->external_id.'/subscribed_apps', [
            'subscribed_fields' => 'messages,message_echoes,feed',
        ], (string) $page->access_token);

        if (empty($result['success'])) {
            throw new SocialException('Meta tidak mengonfirmasi langganan webhook Page.');
        }

        $page->forceFill(['meta' => array_merge($page->meta ?? [], ['inbox_subscribed_at' => now()->toIso8601String()])])->save();
    }

    /* ---------------------------------------------------------------- YouTube (polling) */

    /** Ambil 50 komentar terbaru di semua video channel. @return int jumlah komentar baru */
    public function pollYoutube(SocialAccount $account): int
    {
        $this->social->ensureToken($account);
        $data = $this->youtube('get', 'commentThreads', [
            'part' => 'snippet,replies',
            'allThreadsRelatedToChannelId' => $account->external_id,
            'maxResults' => 50,
            'order' => 'time',
            'textFormat' => 'plainText',
        ], $account);

        $new = 0;

        foreach ((array) ($data['items'] ?? []) as $item) {
            $top = $item['snippet']['topLevelComment'] ?? null;

            if (! is_array($item) || empty($item['id']) || ! is_array($top) || empty($top['id'])) {
                continue;
            }

            $videoId = (string) ($item['snippet']['videoId'] ?? $top['snippet']['videoId'] ?? '');
            $author = $top['snippet'] ?? [];
            $own = (string) ($author['authorChannelId']['value'] ?? '') === $account->external_id;

            $thread = $this->thread($account, SocialThread::KIND_COMMENT, (string) $item['id'], [
                'context_id' => $videoId ?: null,
                'context_url' => $videoId !== '' ? 'https://www.youtube.com/watch?v='.rawurlencode($videoId).'&lc='.rawurlencode((string) $top['id']) : null,
                'meta' => ['top_comment_id' => (string) $top['id']],
            ] + ($own ? [] : [
                'contact_id' => self::clip($author['authorChannelId']['value'] ?? null),
                'contact_name' => self::clip($author['authorDisplayName'] ?? null),
            ]));

            $comments = array_merge([$top], array_values(array_filter((array) ($item['replies']['comments'] ?? []), 'is_array')));

            foreach ($comments as $comment) {
                $snippet = (array) ($comment['snippet'] ?? []);

                if (empty($comment['id'])) {
                    continue;
                }

                $mine = (string) ($snippet['authorChannelId']['value'] ?? '') === $account->external_id;
                $created = $this->record($thread, [
                    'external_id' => (string) $comment['id'],
                    'direction' => $mine ? 'out' : 'in',
                    'author_name' => self::clip($snippet['authorDisplayName'] ?? null),
                    'text' => mb_substr((string) ($snippet['textOriginal'] ?? $snippet['textDisplay'] ?? ''), 0, 5000),
                    'via' => 'platform',
                    'sent_at' => self::time($snippet['publishedAt'] ?? null),
                ]);

                $new += $created && ! $mine ? 1 : 0;
            }
        }

        return $new;
    }

    /* ---------------------------------------------------------------- Balasan */

    /**
     * Kirim balasan lewat API platform lalu simpan. Gagal = pesan tersimpan dengan status failed + SocialException.
     * $via: admin | ai.
     */
    public function reply(SocialThread $thread, string $text, ?User $user, string $via): SocialMessage
    {
        $text = trim($text);
        $account = $thread->account;
        $limit = $thread->kind === SocialThread::KIND_DM ? self::MAX_DM : self::MAX_COMMENT;

        if ($text === '' || mb_strlen($text) > $limit) {
            throw new SocialException('Balasan harus diisi dan maksimal '.$limit.' karakter.');
        }

        if (! $account || ! $account->isConnected()) {
            throw new SocialException('Akun '.($account?->platformLabel() ?? 'sosmed').' perlu disambungkan ulang di Tools › Akun Sosmed.');
        }

        if (! $thread->canReply()) {
            throw new SocialException('Sudah lewat 24 jam sejak pesan terakhir orang ini. Aturan Meta tidak mengizinkan balasan lagi sampai dia mengirim pesan baru.');
        }

        try {
            $externalId = match ($account->platform) {
                'facebook', 'instagram' => $thread->kind === SocialThread::KIND_DM ? $this->sendMetaDm($account, $thread, $text) : $this->sendMetaComment($account, $thread, $text),
                'youtube' => $this->sendYoutubeComment($account, $thread, $text),
                default => throw new SocialException('Platform ini belum bisa dibalas dari Inbox.'),
            };
        } catch (SocialException $e) {
            if ($e->expired) {
                $account->markExpired($e->getMessage());
            }

            $this->record($thread, [
                'direction' => 'out', 'text' => $text, 'via' => $via, 'sent_by' => $user?->getKey(),
                'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 300), 'sent_at' => now(),
            ]);

            throw $e;
        }

        $message = $this->record($thread, [
            'external_id' => $externalId ?: null, 'direction' => 'out', 'author_name' => $account->name, 'text' => $text,
            'via' => $via, 'sent_by' => $user?->getKey(), 'sent_at' => now(),
        ]) ?? $thread->messages()->where('external_id', $externalId)->firstOrFail();

        $thread->forceFill(['unread_count' => 0, 'needs_human' => $via === 'admin' ? false : $thread->needs_human] + ($via === 'admin' ? ['handler' => 'admin'] : []))->save();

        return $message;
    }

    private function sendMetaDm(SocialAccount $account, SocialThread $thread, string $text): string
    {
        $pageId = $account->platform === 'instagram' ? (string) (($account->meta ?? [])['page_id'] ?? '') : $account->external_id;

        if ($pageId === '') {
            throw new SocialException('Page Facebook untuk akun Instagram ini tidak ditemukan. Sambungkan ulang akun Meta.');
        }

        $result = $this->graph()->post($pageId.'/messages', [
            'recipient' => json_encode(['id' => $thread->external_id]),
            'messaging_type' => 'RESPONSE',
            'message' => json_encode(['text' => $text]),
        ], (string) $account->access_token, 30);

        return (string) ($result['message_id'] ?? '');
    }

    private function sendMetaComment(SocialAccount $account, SocialThread $thread, string $text): string
    {
        // Facebook: balasan di bawah komentar utama. Instagram: endpoint replies (balasan satu tingkat).
        $edge = $account->platform === 'instagram' ? 'replies' : 'comments';
        $result = $this->graph()->post($thread->external_id.'/'.$edge, ['message' => $text], (string) $account->access_token, 30);

        return (string) ($result['id'] ?? '');
    }

    private function sendYoutubeComment(SocialAccount $account, SocialThread $thread, string $text): string
    {
        $parentId = (string) (($thread->meta ?? [])['top_comment_id'] ?? '');

        if ($parentId === '') {
            throw new SocialException('Komentar YouTube ini tidak punya ID komentar utama.');
        }

        $this->social->ensureToken($account);
        $result = $this->youtube('post', 'comments?part=snippet', ['snippet' => ['parentId' => $parentId, 'textOriginal' => $text]], $account);

        return (string) ($result['id'] ?? '');
    }

    /* ---------------------------------------------------------------- Penyimpanan */

    /** Percakapan untuk (akun, jenis, ID). Aman dipanggil bersamaan (unik di database). */
    private function thread(SocialAccount $account, string $kind, string $externalId, array $attributes): SocialThread
    {
        $key = ['social_account_id' => $account->getKey(), 'kind' => $kind, 'external_id' => mb_substr($externalId, 0, 191)];
        $attributes = array_filter($attributes, fn ($value) => $value !== null && $value !== '');

        try {
            $thread = SocialThread::where($key)->first() ?? (new SocialThread())->forceFill($key + $attributes + ['status' => 'open', 'handler' => 'ai']);

            if ($thread->exists) {
                // Lengkapi data yang dulu belum ada (mis. nama), jangan timpa yang sudah terisi.
                foreach ($attributes as $column => $value) {
                    if (blank($thread->{$column})) {
                        $thread->{$column} = $value;
                    }
                }
            }

            $thread->save();
        } catch (UniqueConstraintViolationException) {
            $thread = SocialThread::where($key)->firstOrFail();
        }

        return $thread->setRelation('account', $account);
    }

    /**
     * Simpan satu pesan + perbarui ringkasan percakapan. Pesan dengan ID platform yang sama tidak disimpan dua kali
     * (webhook dikirim ulang, atau echo balasan kita sendiri). @return SocialMessage|null null = sudah ada.
     */
    private function record(SocialThread $thread, array $data): ?SocialMessage
    {
        $data += ['status' => 'sent', 'sent_at' => now()];

        if (! empty($data['external_id']) && ($existing = $thread->messages()->where('external_id', $data['external_id'])->first())) {
            $this->claimEcho($existing, $data);

            return null;
        }

        try {
            $message = $thread->messages()->forceCreate($data);
        } catch (UniqueConstraintViolationException) {
            $this->claimEcho($thread->messages()->where('external_id', $data['external_id'])->first(), $data);

            return null;
        }

        if ($message->status === 'failed') {
            return $message;
        }

        $inbound = $message->direction === 'in';
        $updates = [
            'last_preview' => mb_substr((string) ($message->text ?: (! empty($message->attachments) ? '[Lampiran]' : '')), 0, 250),
            'last_message_at' => max($thread->last_message_at ?? $message->sent_at, $message->sent_at),
        ];

        if ($inbound) {
            $updates += [
                'last_inbound_at' => max($thread->last_inbound_at ?? $message->sent_at, $message->sent_at),
                'unread_count' => $thread->unread_count + 1,
                'status' => 'open',
            ];
        }

        $thread->forceFill($updates)->save();

        if ($inbound) {
            $this->maybeAutoReply($thread, $message);
        }

        return $message;
    }

    /** Echo dari Meta untuk balasan yang kita kirim sendiri: pertahankan catatan siapa pengirimnya. */
    private function claimEcho(?SocialMessage $existing, array $data): void
    {
        if ($existing && $existing->via === 'platform' && in_array($data['via'] ?? '', ['admin', 'ai'], true)) {
            $existing->forceFill(['via' => $data['via'], 'sent_by' => $data['sent_by'] ?? null])->save();
        }
    }

    /** Antrekan balasan AI (diputuskan ulang di job: mode, pemegang, batas per jam). */
    private function maybeAutoReply(SocialThread $thread, SocialMessage $message): void
    {
        $setting = SocialInboxSetting::current();

        if ($thread->handler !== 'ai' || $thread->needs_human || ! $setting->autoFor((string) $thread->account?->platform)
            || $message->sent_at?->lt(now()->subHours(SocialAgent::FRESH_HOURS))) {
            return;
        }

        // Jeda sebentar: pesan beruntun dijawab sekali.
        AutoReplySocial::dispatch($thread->getKey(), $message->getKey())->delay(now()->addSeconds(20));
    }

    /* ---------------------------------------------------------------- Bantuan */

    private function graph(): MetaGraph
    {
        return new MetaGraph(SocialApp::for('meta')?->graphVersion() ?? SocialApp::DEFAULT_GRAPH_VERSION);
    }

    private function youtube(string $method, string $path, array $data, SocialAccount $account): array
    {
        try {
            $request = Http::timeout(30)->connectTimeout(15)->acceptJson()->withToken((string) $account->access_token);
            $response = $method === 'get' ? $request->get(self::YOUTUBE_API.'/'.$path, $data) : $request->asJson()->post(self::YOUTUBE_API.'/'.$path, $data);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('YouTube sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        $reason = (string) $response->json('error.errors.0.reason', '');
        report(new \RuntimeException('YouTube '.$response->status().' '.$reason));

        if ($response->status() === 401) {
            throw new SocialException('Izin YouTube sudah tidak berlaku. Sambungkan ulang akun YouTube.', true);
        }

        if (in_array($reason, ['insufficientPermissions', 'ACCESS_TOKEN_SCOPE_INSUFFICIENT', 'forbidden'], true) || $response->status() === 403) {
            throw new SocialException('Akun YouTube belum memberi izin kelola komentar. Sambungkan ulang YouTube di Tools › Akun Sosmed.');
        }

        throw new SocialException('YouTube: '.strip_tags((string) ($response->json('error.message') ?? 'HTTP '.$response->status())));
    }

    /** Satu event gagal tidak menghentikan event lain di webhook yang sama. */
    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function attachments(mixed $items): ?array
    {
        $list = [];

        foreach ((array) $items as $item) {
            if (is_array($item)) {
                $url = (string) ($item['payload']['url'] ?? '');
                $list[] = ['type' => mb_substr((string) ($item['type'] ?? 'file'), 0, 20), 'url' => str_starts_with($url, 'https://') ? mb_substr($url, 0, 1000) : null];
            }
        }

        return $list ?: null;
    }

    private static function time(mixed $value): Carbon
    {
        // Selalu ke zona waktu aplikasi: Eloquent menyimpan jam apa adanya, jadi waktu UTC dari API harus dikonversi dulu.
        try {
            return (match (true) {
                // Messenger memakai milidetik, webhook feed Page memakai detik.
                is_numeric($value) => (int) $value > 100_000_000_000 ? Carbon::createFromTimestampMs((int) $value) : Carbon::createFromTimestamp((int) $value),
                is_string($value) && $value !== '' => Carbon::parse($value),
                default => now(),
            })->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return now();
        }
    }

    private static function clip(mixed $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 191);
    }
}
