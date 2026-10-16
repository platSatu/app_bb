<?php

namespace App\Services\WhatsApp;

use App\Models\WaChat;
use App\Models\WaDevice;
use App\Models\WaMessage;

/** Bentuk data chat & pesan untuk inbox. URL media/avatar lewat bizbos (cek login + branch). */
class InboxPresenter
{
    public function chat(WaDevice $device, WaChat $chat): array
    {
        return [
            'id' => $chat->id,
            'chat_jid' => $chat->chat_jid,
            'name' => $chat->name,
            'phone' => $chat->phone,
            'is_group' => $chat->is_group,
            'unread_count' => (int) $chat->unread_count,
            'last_message' => $chat->last_message_preview,
            'last_message_at' => $chat->last_message_at?->toIso8601String(),
            'assignee' => $chat->assignee?->name,
            'avatar_url' => route('whatsapp.inbox.avatar', [$device, $chat]),
        ];
    }

    public function message(WaDevice $device, WaMessage $message): array
    {
        return [
            'id' => $message->id,
            'wa_id' => $message->wa_id,
            'from_me' => $message->from_me,
            'type' => $message->type,
            'body' => $message->body,
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'sender_name' => $message->sender_name,
            'media_name' => $message->media_name,
            'media_url' => $message->hasMedia() ? route('whatsapp.inbox.media', [$device, $message]) : null,
            'poll_options' => $message->meta['options'] ?? null,
        ];
    }
}
