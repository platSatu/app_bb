<?php

namespace App\Services\WhatsApp\Bot;

use App\Models\WaChat;

/** Pencocokan teks yang sama untuk auto reply & chatbot flow. */
trait BotText
{
    public function matches(string $type, array $keywords, string $text): bool
    {
        if ($type === 'any') {
            return true;
        }
        foreach ($keywords as $keyword) {
            $keyword = $this->normalize($keyword);
            if ($keyword !== '' && match ($type) {
                'exact' => $text === $keyword,
                'starts_with' => str_starts_with($text, $keyword),
                default => preg_match('/(^|\W)'.preg_quote($keyword, '/').'($|\W)/u', $text) === 1,
            }) {
                return true;
            }
        }

        return false;
    }

    public function normalize(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $text)));
    }

    public function personalize(string $text, WaChat $chat): string
    {
        return str_ireplace('{nama}', trim((string) $chat->name) ?: 'Kak', $text);
    }
}
