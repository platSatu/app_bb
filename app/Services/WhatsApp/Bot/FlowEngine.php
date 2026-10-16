<?php

namespace App\Services\WhatsApp\Bot;

use App\Models\WaBotSession;
use App\Models\WaChat;
use App\Models\WaChatbotFlow;

/** Menjalankan chatbot flow: kirim pesan node, terima pilihan (angka atau teks), pindah ke node berikutnya. */
class FlowEngine
{
    use BotText;

    private const MAX_INVALID = 2;
    private const HANDOVER_MINUTES = 120;

    public function continue(WaChat $chat, string $text): ?BotReply
    {
        $session = WaBotSession::with('flow')->where('chat_id', $chat->id)->first();
        if (! $session) {
            return null;
        }

        $flow = $session->flow;
        $node = $flow?->is_active ? $flow->node($session->node) : null;
        if (! $node || $session->expires_at->isPast()) {
            $session->delete();

            return null;
        }

        $option = collect($node['options'] ?? [])->first(fn ($o) => $text === $this->normalize($o['key']) || $text === $this->normalize($o['label']));

        if (! $option) {
            // Kata kunci flow lain (mis. "menu") memulai ulang; terlalu sering salah pilih -> lepas ke aturan berikutnya.
            if ($this->findFlow($chat, $text) || $session->invalid_count >= self::MAX_INVALID) {
                $session->delete();

                return null;
            }
            $session->forceFill(['invalid_count' => $session->invalid_count + 1, 'expires_at' => now()->addMinutes($flow->timeout_minutes)])->save();

            return new BotReply("Maaf, pilihannya belum kami kenali 🙏\n\n".$this->render($node, $chat));
        }

        return $this->follow($chat, $session, $flow, $option);
    }

    public function start(WaChat $chat, string $text): ?BotReply
    {
        $flow = $this->findFlow($chat, $text);
        $node = $flow?->node('start');
        if (! $node) {
            return null;
        }

        if (! empty($node['options'])) {
            WaBotSession::updateOrCreate(['chat_id' => $chat->id], ['flow_id' => $flow->id, 'node' => 'start', 'invalid_count' => 0, 'expires_at' => now()->addMinutes($flow->timeout_minutes)]);
        }

        return new BotReply($this->render($node, $chat));
    }

    private function follow(WaChat $chat, WaBotSession $session, WaChatbotFlow $flow, array $option): BotReply
    {
        $action = $option['action'] ?? 'next';
        $next = $action === 'next' ? $flow->node((string) ($option['next'] ?? '')) : null;

        if ($action === 'handover') {
            $session->delete();

            return new BotReply($this->personalize($flow->handover_message ?: 'Baik {nama}, admin kami akan segera membalas ya 🙏', $chat), true, self::HANDOVER_MINUTES);
        }

        if (! $next) {
            $session->delete();

            return new BotReply($flow->end_message ? $this->personalize($flow->end_message, $chat) : '');
        }

        if (empty($next['options'])) {
            $session->delete();
        } else {
            $session->forceFill(['node' => $next['id'], 'invalid_count' => 0, 'expires_at' => now()->addMinutes($flow->timeout_minutes)])->save();
        }

        return new BotReply($this->render($next, $chat));
    }

    private function findFlow(WaChat $chat, string $text): ?WaChatbotFlow
    {
        return WaChatbotFlow::where('branch_id', $chat->branch_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('device_id')->orWhere('device_id', $chat->device_id))
            ->orderByRaw("match_type = 'exact' desc")->get()
            ->first(fn ($flow) => $this->matches($flow->match_type, $flow->keywords ?? [], $text));
    }

    private function render(array $node, WaChat $chat): string
    {
        $text = $this->personalize((string) $node['message'], $chat);
        $options = collect($node['options'] ?? [])->map(fn ($o) => '*'.$o['key'].'*. '.$o['label']);

        return $options->isEmpty() ? $text : $text."\n\n".$options->join("\n")."\n\nBalas dengan angka pilihan Anda.";
    }
}
