<?php

namespace App\Listeners;

use App\Events\WaWebhookReceived;
use App\Jobs\ProcessIncomingWaMessage;
use App\Models\WaChat;

/** Pesan masuk -> antrean bot. Balasan dari HP (bukan lewat bizbos) -> tandai admin sedang membalas. */
class RouteWaWebhook
{
    public function handle(WaWebhookReceived $event): void
    {
        $data = $event->data;
        if ($event->event !== 'message.new' || empty($data['chat_id']) || ! empty($data['is_group'])) {
            return;
        }

        $message = $data['message'] ?? [];
        if (! empty($message['from_me'])) {
            if (empty($data['outbox'])) {
                WaChat::whereKey($data['chat_id'])->where('branch_id', $event->branchId)->update(['human_replied_at' => now()]);
            }

            return;
        }

        if (($message['type'] ?? '') === 'text' && ! empty($message['id'])) {
            ProcessIncomingWaMessage::dispatch($message['id']);
        }
    }
}
