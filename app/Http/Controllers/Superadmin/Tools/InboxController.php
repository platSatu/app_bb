<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialInboxSetting;
use App\Models\SocialThread;
use App\Models\WaChat;
use App\Models\WaDevice;
use App\Services\Social\Inbox\SocialAgent;
use App\Services\Social\Inbox\SocialInbox;
use App\Services\Social\SocialException;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Superadmin › Tools › Inbox: satu daftar untuk WhatsApp milik branch superadmin sendiri + DM/komentar sosmed resmi bizbos.
 * WhatsApp hanya dibaca (dibalas di Device & Inbox yang sudah ada); data WhatsApp tenant tidak pernah ikut tampil.
 */
class InboxController extends Controller
{
    private const LIST_LIMIT = 100;

    public const CHANNELS = ['all' => 'Semua', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'];

    public const FILTERS = ['open' => 'Belum selesai', 'unread' => 'Belum dibaca', 'human' => 'Butuh admin', 'done' => 'Selesai', 'all' => 'Semua'];

    public function __construct(private readonly SocialInbox $inbox, private readonly AppAccess $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(array_keys(self::CHANNELS))],
            'filter' => ['nullable', Rule::in(array_keys(self::FILTERS))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $channel = $filters['channel'] ?? 'all';
        $filter = $filters['filter'] ?? 'open';
        $q = trim((string) ($filters['q'] ?? ''));

        $rows = collect()
            ->merge($channel === 'all' || $channel === 'whatsapp' ? $this->whatsappRows($request, $filter, $q) : [])
            ->merge($channel !== 'whatsapp' ? $this->socialRows($channel, $filter, $q) : [])
            ->sortByDesc('at')->take(self::LIST_LIMIT)->values();

        return view('superadmin.tools.inbox', [
            'rows' => $rows,
            'channel' => $channel,
            'filter' => $filter,
            'q' => $q,
            'setting' => SocialInboxSetting::current(),
            'accounts' => SocialAccount::orderBy('platform')->orderBy('name')->get(),
            'hasWhatsapp' => $this->access->ownerHas($request->user(), 'whatsapp'),
            'counts' => [
                'human' => SocialThread::where('needs_human', true)->where('status', 'open')->count(),
                'unread' => SocialThread::where('unread_count', '>', 0)->count(),
            ],
        ]);
    }

    public function show(SocialThread $thread): View
    {
        $thread->load('account');

        if ($thread->unread_count > 0) {
            $thread->forceFill(['unread_count' => 0])->save();
        }

        return view('superadmin.tools.inbox-thread', [
            'thread' => $thread,
            'messages' => $thread->messages()->with('sender:id,name')->orderByDesc('sent_at')->orderByDesc('created_at')->limit(100)->get()->reverse()->values(),
            'setting' => SocialInboxSetting::current(),
        ]);
    }

    public function reply(Request $request, SocialThread $thread): RedirectResponse
    {
        $limit = $thread->kind === SocialThread::KIND_DM ? SocialInbox::MAX_DM : SocialInbox::MAX_COMMENT;
        $data = $request->validate(['text' => ['required', 'string', 'max:'.$limit]], [], ['text' => 'Balasan']);

        try {
            $this->inbox->reply($thread->load('account'), $data['text'], $request->user(), 'admin');
        } catch (SocialException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Balasan terkirim. Percakapan ini sekarang dipegang admin (AI berhenti).');
    }

    /** Draf AI untuk diperiksa admin sebelum dikirim. */
    public function draft(SocialThread $thread, SocialAgent $agent): JsonResponse
    {
        if (! SocialInboxSetting::current()->aiEnabled()) {
            return response()->json(['message' => 'Aktifkan AI (mode Draf atau Otomatis) di Pengaturan Inbox dulu.'], 422);
        }

        try {
            $text = $agent->draft($thread->load('account'));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'AI belum bisa membuat draf: '.mb_substr($e->getMessage(), 0, 200)], 422);
        }

        return $text === null
            ? response()->json(['message' => 'AI menyarankan admin yang menjawab percakapan ini.'], 422)
            : response()->json(['text' => $text]);
    }

    public function update(Request $request, SocialThread $thread): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'done'])],
            'handler' => ['nullable', Rule::in(['ai', 'admin'])],
        ]);

        $changes = array_filter($data);

        // Diserahkan ke AI / ditandai selesai = tanda "butuh admin" sudah ditangani.
        if (($changes['handler'] ?? null) === 'ai' || ($changes['status'] ?? null) === 'done') {
            $changes['needs_human'] = false;
        }

        $thread->forceFill($changes)->save();

        return back()->with('success', match (true) {
            ($changes['handler'] ?? null) === 'ai' => 'Percakapan diserahkan ke AI. AI hanya membalas pesan baru kalau mode Otomatis aktif.',
            ($changes['handler'] ?? null) === 'admin' => 'Percakapan dipegang admin. AI tidak akan membalas.',
            ($changes['status'] ?? null) === 'done' => 'Percakapan ditandai selesai.',
            default => 'Percakapan dibuka lagi.',
        });
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_mode' => ['required', Rule::in(array_keys(SocialInboxSetting::AI_MODES))],
            'ai_platforms' => ['nullable', 'array'],
            'ai_platforms.*' => [Rule::in(SocialInboxSetting::AI_PLATFORMS)],
            'ai_instructions' => ['nullable', 'string', 'max:5000'],
            'ai_max_per_hour' => ['required', 'integer', 'min:1', 'max:200'],
        ], [], ['ai_instructions' => 'Info untuk AI', 'ai_max_per_hour' => 'Batas balasan AI per jam']);

        SocialInboxSetting::current()->fill([
            'ai_mode' => $data['ai_mode'],
            'ai_platforms' => array_values(array_unique($data['ai_platforms'] ?? [])),
            'ai_instructions' => filled($data['ai_instructions'] ?? null) ? trim($data['ai_instructions']) : SocialInboxSetting::DEFAULT_INSTRUCTIONS,
            'ai_max_per_hour' => $data['ai_max_per_hour'],
            'updated_by' => $request->user()->getKey(),
        ])->save();

        return back()->with('success', 'Pengaturan Inbox disimpan.');
    }

    /** Daftarkan Page (dan Instagram yang terhubung) ke webhook app Meta. */
    public function subscribe(SocialAccount $account): RedirectResponse
    {
        try {
            $this->inbox->subscribePage($account);
        } catch (SocialException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Webhook aktif untuk '.$account->name.'. Pesan & komentar baru akan masuk ke Inbox.');
    }

    public function syncYoutube(SocialAccount $account): RedirectResponse
    {
        abort_unless($account->platform === 'youtube', 404);

        try {
            $new = $this->inbox->pollYoutube($account);
        } catch (SocialException $e) {
            if ($e->expired) {
                $account->markExpired($e->getMessage());
            }

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $new.' komentar YouTube baru diambil.');
    }

    /** Chat WhatsApp di device milik branch superadmin sendiri (paket WhatsApp aktif). Grup tidak ditampilkan. */
    private function whatsappRows(Request $request, string $filter, string $q): Collection
    {
        if (in_array($filter, ['done', 'human'], true)) {
            return collect(); // status selesai / butuh admin WhatsApp diatur di Device & Inbox
        }

        $branchIds = $this->access->ownerBranchIds($request->user(), 'whatsapp');

        if ($branchIds === []) {
            return collect();
        }

        $devices = WaDevice::whereIn('branch_id', $branchIds)->pluck('name', 'id');

        return WaChat::whereIn('device_id', $devices->keys())->where('is_group', false)->whereNotNull('last_message_at')
            ->when($filter === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('phone', 'like', '%'.addcslashes(preg_replace('/\D/', '', $q) ?: $q, '%_\\').'%')))
            ->orderByDesc('last_message_at')->limit(self::LIST_LIMIT)->get()
            ->map(fn (WaChat $chat) => [
                'channel' => 'whatsapp',
                'label' => 'WhatsApp',
                'icon' => 'ri-whatsapp-line',
                'color' => '#25D366',
                'kind' => 'Chat',
                'name' => $chat->displayName(),
                'account' => (string) ($devices[$chat->device_id] ?? ''),
                'preview' => (string) $chat->last_message_preview,
                'at' => $chat->last_message_at,
                'unread' => (int) $chat->unread_count,
                'human' => false,
                'handler' => null,
                'status' => null,
                'url' => route('whatsapp.inbox.show', $chat->device_id),
            ]);
    }

    private function socialRows(string $channel, string $filter, string $q): Collection
    {
        return SocialThread::with('account')
            ->when($channel !== 'all', fn ($query) => $query->whereHas('account', fn ($a) => $a->where('platform', $channel)))
            ->when($filter === 'open', fn ($query) => $query->where('status', 'open'))
            ->when($filter === 'done', fn ($query) => $query->where('status', 'done'))
            ->when($filter === 'unread', fn ($query) => $query->where('unread_count', '>', 0))
            ->when($filter === 'human', fn ($query) => $query->where('needs_human', true)->where('status', 'open'))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('contact_name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('contact_handle', 'like', '%'.addcslashes(ltrim($q, '@'), '%_\\').'%')
                ->orWhere('last_preview', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')->limit(self::LIST_LIMIT)->get()
            ->filter(fn (SocialThread $thread) => $thread->account !== null)
            ->map(fn (SocialThread $thread) => [
                'channel' => $thread->account->platform,
                'label' => $thread->account->platformLabel(),
                'icon' => $thread->account->platformIcon(),
                'color' => $thread->account->platformColor(),
                'kind' => $thread->kindLabel(),
                'name' => $thread->displayName(),
                'account' => $thread->account->name,
                'preview' => (string) $thread->last_preview,
                'at' => $thread->last_message_at,
                'unread' => $thread->unread_count,
                'human' => $thread->needs_human,
                'handler' => $thread->handler,
                'status' => $thread->status,
                'url' => route('superadmin.tools.inbox.show', $thread),
            ]);
    }
}
