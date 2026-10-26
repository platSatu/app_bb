<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaChat;
use App\Models\WaDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Lonceng di header: chat yang belum dibaca di semua nomor WhatsApp milik owner, terbaru di atas. */
class NotificationController extends Controller
{
    use InWhatsAppArea;

    private const LIMIT = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $devices = WaDevice::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->pluck('name', 'id');

        $chats = $devices->isEmpty() ? collect() : WaChat::whereIn('device_id', $devices->keys())
            ->where('unread_count', '>', 0)
            ->where('chat_jid', 'not like', '%@newsletter')   // saluran WhatsApp, bukan percakapan
            ->where('chat_jid', '!=', 'status@broadcast')
            ->orderByDesc('last_message_at')
            ->limit(self::LIMIT + 1)
            ->get(['id', 'device_id', 'name', 'phone', 'is_group', 'unread_count', 'last_message_at', 'last_message_preview']);

        return response()->json([
            'count' => min($chats->count(), self::LIMIT),
            'more' => $chats->count() > self::LIMIT,
            'items' => $chats->take(self::LIMIT)->map(fn (WaChat $chat) => [
                'name' => $chat->displayName(),
                'device' => $devices[$chat->device_id],
                'preview' => Str::limit((string) $chat->last_message_preview, 80),
                'unread' => (int) $chat->unread_count,
                'at' => $chat->last_message_at?->toIso8601String(),
                'url' => route('whatsapp.inbox.show', [$chat->device_id, 'chat' => $chat->id]),
            ])->values(),
        ]);
    }
}
