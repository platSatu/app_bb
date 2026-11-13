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
 *   3. batas balasan tercapai -> diserahkan ke admin (satu pesan), bot dijeda
 *   4. sedang di chatbot flow -> flow melanjutkan
 *   5. kata kunci flow       -> flow dimulai
 *   6. auto reply cocok      -> auto reply
 *   7. AI bot aktif          -> AI menjawab
 */
class BotRouter
{
    use BotText;

    // 14 November 2026: 30 -> 5 menit. Bot menyingkir begitu admin ikut membalas, tapi
    // kembali berjaga cepat supaya chat yang masuk setelahnya tidak ikut didiamkan lama.
    // Tiap balasan admin memperpanjang jeda ini, jadi percakapan yang masih berjalan tetap aman.
    public const HUMAN_HOLD_MINUTES = 5;
    // Batas balasan dipakai untuk memutus bot yang saling balas, bukan untuk membatasi orang:
    // menu bertingkat wajar dipakai belasan kali, sedangkan dua mesin saling balas selalu beruntun
    // dalam hitungan detik. Karena itu batas panjangnya longgar dan batas beruntunnya yang ketat.
    private const MAX_REPLIES = 20;      // per chat per 10 menit
    private const BURST_REPLIES = 6;     // per chat per BURST_SECONDS; manusia tidak sampai secepat ini
    private const BURST_SECONDS = 12;
    private const MAX_AGE_SECONDS = 300; // pesan lama (riwayat) tidak dijawab

    private const STOP_WORDS = ['stop', 'berhenti', 'unsubscribe'];

    public function __construct(private WaGateway $wa, private FlowEngine $flows, private AiResponder $ai) {}

    public function handle(string $messageId): void
    {
        $message = WaMessage::with('chat.device')->find($messageId);
        $chat = $message?->chat;

        if (! $message || ! $chat || $message->from_me || $chat->is_group || $message->type !== 'text'
            || ! app(\App\Services\Subscription\AppAccess::class)->branchActive($chat->branch_id, 'whatsapp')
            || blank($message->body) || $message->sent_at->diffInSeconds(now()) > self::MAX_AGE_SECONDS) {
            return;
        }

        // Pesan beruntun dari kontak yang sama diproses bergantian, tidak bersamaan.
        Cache::lock('wa-bot-chat:'.$chat->id, 60)->block(20, fn () => $this->route($chat->refresh(), $message));
    }

    private function route(WaChat $chat, WaMessage $message): void
    {
        $text = $this->normalize($message->body);

        if (in_array($text, self::STOP_WORDS, true) || $this->humanIsHandling($chat)) {
            return;
        }

        // Batas tercapai: chat TIDAK didiamkan, tapi diserahkan ke admin dengan satu pesan.
        // Bot dijeda lebih dulu, jadi pesan ini sendiri tidak ikut jadi balas-balasan.
        if (! $this->withinRateLimit($chat)) {
            $this->pauseBot($chat, self::HUMAN_HOLD_MINUTES);
            $this->send($chat, 'Maaf Kak, biar lebih cepat saya sambungkan ke tim kami ya 🙏 Mohon tunggu sebentar.');

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
            $this->pauseBot($chat, $reply->handoverMinutes);
        }
        if ($reply->text !== '') {
            $this->send($chat, $reply->text);
        }
    }

    /** Satu-satunya jalan keluar pesan bot: pemakaian jatah balasan selalu ikut tercatat di sini. */
    private function send(WaChat $chat, string $text): void
    {
        RateLimiter::hit($this->limiterKey($chat), 600);
        RateLimiter::hit($this->burstKey($chat), self::BURST_SECONDS);
        $this->wa->sendText($chat->branch_id, [
            'device_id' => $chat->device_id,
            'to' => $chat->chat_jid,
            'text' => $text,
            'client_ref' => 'bot:'.Str::uuid(),
        ]);
    }

    private function pauseBot(WaChat $chat, int $minutes): void
    {
        $chat->forceFill(['bot_paused_until' => now()->addMinutes($minutes)])->save();
    }

    private function humanIsHandling(WaChat $chat): bool
    {
        return $chat->assigned_user_id !== null
            || $chat->bot_paused_until?->isFuture()
            || $chat->human_replied_at?->gt(now()->subMinutes(self::HUMAN_HOLD_MINUTES));
    }

    private function withinRateLimit(WaChat $chat): bool
    {
        return ! RateLimiter::tooManyAttempts($this->limiterKey($chat), self::MAX_REPLIES)
            && ! RateLimiter::tooManyAttempts($this->burstKey($chat), self::BURST_REPLIES);
    }

    private function limiterKey(WaChat $chat): string
    {
        return 'wa-bot-replies:'.$chat->id;
    }

    private function burstKey(WaChat $chat): string
    {
        return 'wa-bot-burst:'.$chat->id;
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