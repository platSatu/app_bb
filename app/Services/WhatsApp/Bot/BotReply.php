<?php

namespace App\Services\WhatsApp\Bot;

/** Jawaban bot: teks yang dikirim, dan apakah percakapan diserahkan ke admin. */
final class BotReply
{
    public function __construct(
        public readonly string $text,
        public readonly bool $handover = false,
        public readonly int $handoverMinutes = 120,
    ) {}
}
