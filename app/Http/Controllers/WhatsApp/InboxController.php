<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaMessage;
use App\Models\WaQuickReply;
use App\Services\WhatsApp\InboxPresenter;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Inbox satu device: daftar chat, isi percakapan, kirim teks/file/polling. Update realtime lewat SSE go_bizbos. */
class InboxController extends Controller
{
    use InWhatsAppArea;

    private const MAX_FILE_KB = 64 * 1024;

    public function __construct(private WaGateway $wa, private InboxPresenter $present) {}

    public function show(Request $request, string $device): View
    {
        $model = $this->device($request, $device);

        return view('whatsapp.inbox.show', ['device' => $model->load('branch:id,name')]);
    }

    public function chats(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        $chats = $model->chats()->with('assignee:id,name')
            ->orderByDesc('last_message_at')->limit(300)->get();

        return response()->json(['chats' => $chats->map(fn ($chat) => $this->present->chat($model, $chat))]);
    }

    public function chat(Request $request, string $device, string $chat): JsonResponse
    {
        $model = $this->device($request, $device);

        return response()->json(['chat' => $this->present->chat($model, $this->findChat($model, $chat)->load('assignee:id,name'))]);
    }

    /** 60 pesan terbaru, atau 60 sebelum ?before=<id pesan> saat menggulir ke atas. */
    public function messages(Request $request, string $device, string $chat): JsonResponse
    {
        $model = $this->device($request, $device);
        $thread = $this->findChat($model, $chat);
        $query = $thread->messages()->orderByDesc('sent_at')->orderByDesc('id')->limit(60);

        if ($before = $thread->messages()->find($request->query('before'))) {
            $query->where(fn ($q) => $q->where('sent_at', '<', $before->sent_at)
                ->orWhere(fn ($w) => $w->where('sent_at', $before->sent_at)->where('id', '<', $before->id)));
        }

        $messages = $query->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->present->message($model, $m)),
            'has_more' => $messages->count() === 60,
        ]);
    }

    public function send(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:10000'],
            'quoted_wa_id' => ['nullable', 'string', 'max:128'],
        ] + $this->targetRules());

        return $this->gatewayJson($this->wa->sendText($model->branch_id, [
            'device_id' => $model->id,
            'to' => $this->target($model, $data),
            'text' => $data['text'],
            'quoted_wa_id' => $data['quoted_wa_id'] ?? null,
            'client_ref' => $data['client_ref'] ?? (string) Str::uuid(),
        ]));
    }

    public function sendMedia(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB],
            'caption' => ['nullable', 'string', 'max:3000'],
        ] + $this->targetRules(), ['file.max' => 'Ukuran file maksimal 64 MB.']);

        return $this->gatewayJson($this->wa->sendMedia($model->branch_id, [
            'device_id' => $model->id,
            'to' => $this->target($model, $data),
            'text' => $data['caption'] ?? null,
            'client_ref' => $data['client_ref'] ?? (string) Str::uuid(),
        ], $request->file('file')));
    }

    public function sendPoll(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:2', 'max:12'],
            'options.*' => ['required', 'string', 'max:100', 'distinct'],
            'selectable_count' => ['nullable', 'integer', 'min:1', 'max:12'],
        ] + $this->targetRules(), ['options.min' => 'Minimal 2 pilihan jawaban.', 'options.*.distinct' => 'Pilihan jawaban tidak boleh sama.']);

        return $this->gatewayJson($this->wa->sendPoll($model->branch_id, [
            'device_id' => $model->id,
            'to' => $this->target($model, $data),
            'question' => $data['question'],
            'options' => array_values($data['options']),
            'selectable_count' => $data['selectable_count'] ?? 1,
            'client_ref' => $data['client_ref'] ?? (string) Str::uuid(),
        ]));
    }

    public function pollResults(Request $request, string $device, string $message): JsonResponse
    {
        $model = $this->device($request, $device);
        $poll = WaMessage::where('device_id', $model->id)->where('type', 'poll')->findOrFail($message);
        $options = $poll->meta['options'] ?? [];
        $tally = array_fill_keys($options, 0);

        foreach ($poll->pollVotes as $vote) {
            foreach ($vote->selected_options ?? [] as $option) {
                if (array_key_exists($option, $tally)) {
                    $tally[$option]++;
                }
            }
        }

        return response()->json(['question' => $poll->body, 'tally' => $tally, 'voters' => $poll->pollVotes->count()]);
    }

    public function read(Request $request, string $device, string $chat): JsonResponse
    {
        $model = $this->device($request, $device);

        return $this->gatewayJson($this->wa->markRead($model->branch_id, $this->findChat($model, $chat)->id));
    }

    public function presence(Request $request, string $device, string $chat): JsonResponse
    {
        $model = $this->device($request, $device);

        return $this->gatewayJson($this->wa->subscribePresence($model->branch_id, $this->findChat($model, $chat)->id));
    }

    /** File pesan: diarahkan ke go_bizbos dengan token singkat, jadi tautan di halaman tidak pernah kedaluwarsa. */
    public function media(Request $request, string $device, string $message): RedirectResponse
    {
        $model = $this->device($request, $device);
        $msg = WaMessage::where('device_id', $model->id)->findOrFail($message);

        return redirect()->away($this->wa->mediaUrl($model->branch_id, $msg->id));
    }

    public function avatar(Request $request, string $device, string $chat): RedirectResponse
    {
        $model = $this->device($request, $device);

        return redirect()->away($this->wa->avatarUrl($model->branch_id, $this->findChat($model, $chat)->id))
            ->header('Cache-Control', 'private, max-age=600');
    }

    public function realtime(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);

        return response()->json(['url' => $this->wa->realtimeUrl($model->branch_id, $model->id)]);
    }

    public function quickReplies(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);

        return response()->json(['quick_replies' => WaQuickReply::where('branch_id', $model->branch_id)->orderBy('title')->get(['id', 'title', 'shortcut', 'body'])]);
    }

    /** Tujuan kirim: chat yang sudah ada (chat_id) atau nomor baru (phone). */
    private function targetRules(): array
    {
        return [
            'chat_id' => ['nullable', 'uuid', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without:chat_id'],
            'client_ref' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    private function target(WaDevice $device, array $data): string
    {
        if (! empty($data['chat_id'])) {
            $chat = $this->findChat($device, $data['chat_id']);
            // Admin membalas dari inbox: bot diam dulu (lihat BotRouter::HUMAN_HOLD_MINUTES).
            $chat->forceFill(['human_replied_at' => now()])->save();

            return $chat->chat_jid;
        }

        $phone = User::normalizePhone($data['phone']);
        abort_if(strlen($phone) < 10, 422, 'Nomor WhatsApp tidak valid.');

        return $phone;
    }
}
