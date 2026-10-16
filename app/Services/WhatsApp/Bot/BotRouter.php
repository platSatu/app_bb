<?php

namespace App\Services\WhatsApp\Bot;

use App\Models\WaAutoReply;
use App\Models\WaChat;
use App\Models\WaMessage;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Satu pesan masuk dijawab paling banyak satu pihak, dicek berurutan:
 *   1. STOP/BERHENTI         -> diam (go_bizbos mencatat opt-out)
 *   2. dipegang manusia      -> diam (di-assign, dijeda, atau admin baru membalas)
 *   3. sedang di chatbot flow -> flow melanjutkan
 *   4. kata kunci flow       -> flow dimulai
 *   5. auto reply cocok      -> auto reply
 *   6. AI bot aktif          -> AI menjawab
 */
class BotRouter
{
    use BotText;

    public const HUMAN_HOLD_MINUTES = 30;
    private const MAX_REPLIES = 5;      // per chat per 10 menit, mencegah bot saling balas
    private const MAX_AGE_SECONDS = 300; // pesan lama (riwayat) tidak dijawab

    private const STOP_WORDS = ['stop', 'berhenti', 'unsubscribe'];

    public function __construct(private WaGateway $wa, private FlowEngine $flows, private AiResponder $ai) {}

    public function handle(string $messageId): void
    {
        $message = WaMessage::with('chat.device')->find($messageId);
        $chat = $message?->chat;

        if (! $message || ! $chat || $message->from_me || $chat->is_group || $message->type !== 'text'
            || blank($message->body) || $message->sent_at->diffInSeconds(now()) > self::MAX_AGE_SECONDS) {
            return;
        }

        // Pesan beruntun dari kontak yang sama diproses bergantian, tidak bersamaan.
        Cache::lock('wa-bot-chat:'.$chat->id, 60)->block(20, fn () => $this->route($chat->refresh(), $message));
    }

    private function route(WaChat $chat, WaMessage $message): void
    {
        $text = $this->normalize($message->body);

        if (in_array($text, self::STOP_WORDS, true) || $this->humanIsHandling($chat) || ! $this->withinRateLimit($chat)) {
            return;
        }

        $reply = $this->flows->continue($chat, $text)
            ?? $this->flows->start($chat, $text)
            ?? $this->autoReply($chat, $text)
            ?? $this->ai->reply($chat, $message);

        if ($reply === null) {
            return;
        }

        if ($reply->handover) {
            $chat->forceFill(['bot_paused_until' => now()->addMinutes($reply->handoverMinutes)])->save();
        }
        if ($reply->text !== '') {
            RateLimiter::hit($this->limiterKey($chat), 600);
            $this->wa->sendText($chat->branch_id, [
                'device_id' => $chat->device_id,
                'to' => $chat->chat_jid,
                'text' => $reply->text,
                'client_ref' => 'bot:'.Str::uuid(),
            ]);
        }
    }

    private function humanIsHandling(WaChat $chat): bool
    {
        return $chat->assigned_user_id !== null
            || $chat->bot_paused_until?->isFuture()
            || $chat->human_replied_at?->gt(now()->subMinutes(self::HUMAN_HOLD_MINUTES));
    }

    private function withinRateLimit(WaChat $chat): bool
    {
        return ! RateLimiter::tooManyAttempts($this->limiterKey($chat), self::MAX_REPLIES);
    }

    private function limiterKey(WaChat $chat): string
    {
        return 'wa-bot-replies:'.$chat->id;
    }

    /** Urutan cocok: sama persis, diawali, mengandung, lalu "semua pesan". Tiap aturan punya jeda ulang per chat. */
    private function autoReply(WaChat $chat, string $text): ?BotReply
    {
        $order = ['exact' => 0, 'starts_with' => 1, 'contains' => 2, 'any' => 3];
        $rules = WaAutoReply::where('branch_id', $chat->branch_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('device_id')->orWhere('device_id', $chat->device_id))
            ->get()->sortBy(fn ($r) => $order[$r->match_type] ?? 9);

        foreach ($rules as $rule) {
            if (! $this->matches($rule->match_type, $rule->keywords ?? [], $text)) {
                continue;
            }
            $cooldown = 'wa-ar:'.$rule->id.':'.$chat->id;
            if (Cache::has($cooldown)) {
                return null; // sudah dibalas aturan ini barusan, jangan diulang
            }
            Cache::put($cooldown, true, now()->addMinutes(max(1, $rule->cooldown_minutes)));

            return new BotReply($this->personalize($rule->body, $chat));
        }

        return null;
    }
}
