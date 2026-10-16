<?php

namespace App\Services\WhatsApp\Bot;

use App\Models\WaAiBot;
use App\Models\WaAiLog;
use App\Models\WaChat;
use App\Models\WaMessage;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/** Jawaban AI dengan API key milik user. Dibatasi per branch supaya satu user tidak mengganggu user lain. */
class AiResponder
{
    private const HANDOVER_TOKEN = '[ADMIN]';
    private const PER_MINUTE_PER_BRANCH = 30;
    private const HISTORY = 12;

    public function reply(WaChat $chat, WaMessage $message): ?BotReply
    {
        $bot = WaAiBot::where('device_id', $chat->device_id)->where('is_active', true)->first();
        if (! $bot) {
            return null;
        }

        $limiter = 'wa-ai:'.$chat->branch_id;
        if (RateLimiter::tooManyAttempts($limiter, self::PER_MINUTE_PER_BRANCH)) {
            $this->log($bot, $chat, 'limited', 0, 'Batas '.self::PER_MINUTE_PER_BRANCH.' jawaban AI per menit tercapai.');

            return null;
        }
        RateLimiter::hit($limiter, 60);

        $started = microtime(true);
        try {
            $text = (new AiClient($bot))->chat($this->system($bot, $chat), $this->history($chat, $message), $bot->max_tokens);
        } catch (Throwable $e) {
            $this->log($bot, $chat, 'failed', $started, $e->getMessage());

            return null;
        }
        $this->log($bot, $chat, 'ok', $started);

        $handover = str_contains($text, self::HANDOVER_TOKEN);
        $text = trim(str_replace(self::HANDOVER_TOKEN, '', $text));

        return new BotReply(mb_substr($text, 0, 4000), $handover, $bot->handover_minutes);
    }

    /** Uji API key dari halaman pengaturan. */
    public function test(WaAiBot $bot): string
    {
        return (new AiClient($bot))->chat($this->system($bot, null), [['role' => 'user', 'content' => 'Halo, ini pesan uji. Perkenalkan diri Anda singkat.']], 200);
    }

    private function system(WaAiBot $bot, ?WaChat $chat): string
    {
        return implode("\n\n", array_filter([
            $bot->system_prompt ?: 'Anda adalah customer service yang ramah dan sopan.',
            $bot->knowledge ? "Informasi bisnis (jawab hanya berdasarkan ini):\n".$bot->knowledge : null,
            $chat?->name ? 'Nama pelanggan: '.$chat->name : null,
            'Aturan: jawab singkat dalam bahasa yang dipakai pelanggan, format WhatsApp (tanpa markdown tabel). '
            .'Jangan mengarang harga, stok, atau janji yang tidak ada di informasi bisnis. '
            .'Bila tidak tahu jawabannya, pelanggan minta bicara dengan manusia, atau pelanggan marah/komplain, '
            .'katakan admin akan segera membantu dan tambahkan '.self::HANDOVER_TOKEN.' di akhir jawaban.',
        ]));
    }

    /** Riwayat singkat, peran berselang-seling dan selalu diawali pelanggan (syarat sebagian penyedia). */
    private function history(WaChat $chat, WaMessage $current): array
    {
        $messages = $chat->messages()->where('type', 'text')->whereNotNull('body')
            ->where('sent_at', '<=', $current->sent_at)->latest('sent_at')->limit(self::HISTORY)->get()->reverse();

        $turns = [];
        foreach ($messages as $m) {
            $role = $m->from_me ? 'assistant' : 'user';
            $content = mb_substr((string) $m->body, 0, 1500);
            if ($turns && end($turns)['role'] === $role) {
                $turns[array_key_last($turns)]['content'] .= "\n".$content;
            } elseif ($turns || $role === 'user') {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }
        while ($turns && end($turns)['role'] === 'assistant') {
            array_pop($turns);
        }

        return $turns ?: [['role' => 'user', 'content' => (string) $current->body]];
    }

    private function log(WaAiBot $bot, WaChat $chat, string $status, float $started, ?string $error = null): void
    {
        WaAiLog::create([
            'bot_id' => $bot->id,
            'chat_id' => $chat->id,
            'status' => $status,
            'latency_ms' => $started ? (int) ((microtime(true) - $started) * 1000) : 0,
            'error' => $error ? mb_substr($error, 0, 255) : null,
        ]);
    }
}
