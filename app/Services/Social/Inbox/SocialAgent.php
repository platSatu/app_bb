<?php

namespace App\Services\Social\Inbox;

use App\Models\SocialInboxSetting;
use App\Models\SocialMessage;
use App\Models\SocialThread;
use App\Services\Ai\AiClient;
use App\Services\Social\SocialException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Agent AI Tools › Inbox. Pengaman:
 *  - hanya membalas percakapan yang dipegang AI (admin membalas = AI berhenti di percakapan itu);
 *  - pesan sensitif (komplain, refund, data pribadi, dll.) atau jawaban yang AI tidak yakin -> ditandai "Butuh admin";
 *  - hanya pesan baru (maks FRESH_HOURS jam), sekali per pesan, dan dibatasi jumlah balasan per jam.
 */
class SocialAgent
{
    public const FRESH_HOURS = 2;

    /** Jawaban AI ini berarti "serahkan ke admin". */
    private const HANDOFF = '[ADMIN]';

    private const SENSITIVE = '/\b(refund|uang\s*kembali|komplain|keluhan|kecewa|penipu\w*|tipu|ditipu|lapor\w*|polisi|pengacara|hukum|somasi|marah|batal\w*|hapus\s*akun|password|kata\s*sandi|otp|pin|rekening|transfer|ktp|nik)\b/iu';

    public function __construct(private readonly SocialInbox $inbox) {}

    /** Draf balasan untuk admin (mode draf / tombol "Buat draf AI"). null = AI menyarankan admin yang menjawab. */
    public function draft(SocialThread $thread): ?string
    {
        $setting = SocialInboxSetting::current();
        $history = $this->history($thread);

        if ($history === []) {
            return null;
        }

        $answer = trim(AiClient::make()->chat($this->system($setting, $thread), $history, 500));
        $answer = trim(preg_replace('/^["“]|["”]$/u', '', $answer));

        if ($answer === '' || str_contains($answer, self::HANDOFF)) {
            return null;
        }

        $limit = $thread->kind === SocialThread::KIND_DM ? SocialInbox::MAX_DM : SocialInbox::MAX_COMMENT;

        return mb_substr($answer, 0, $limit);
    }

    /** Dipanggil job AutoReplySocial. Semua syarat dicek ulang di sini (pengaturan bisa berubah selama antre). */
    public function autoReply(string $threadId, string $messageId): void
    {
        $lock = Cache::lock('social-inbox-ai:'.$threadId, 120);

        if (! $lock->get()) {
            return; // balasan lain untuk percakapan ini sedang diproses
        }

        $thread = null;

        try {
            $thread = SocialThread::with('account')->find($threadId);
            $message = $thread?->messages()->find($messageId);
            $setting = SocialInboxSetting::current();

            if (! $thread || ! $message || $thread->handler !== 'ai' || $thread->needs_human || $thread->status === 'done'
                || ! $setting->autoFor((string) $thread->account?->platform) || ! $thread->canReply()) {
                return;
            }

            // Sudah ada balasan setelah pesan ini (admin, AI, atau dari aplikasi langsung): jangan dobel.
            $latest = $thread->messages()->where('status', 'sent')->orderByDesc('sent_at')->orderByDesc('created_at')->first();

            if (! $latest || $latest->direction !== 'in' || $message->sent_at?->lt(now()->subHours(self::FRESH_HOURS))) {
                return;
            }

            if ($this->sensitive($thread)) {
                $thread->forceFill(['needs_human' => true])->save();

                return;
            }

            $sentLastHour = SocialMessage::where('via', 'ai')->where('status', 'sent')->where('sent_at', '>=', now()->subHour())->count();

            if ($sentLastHour >= max(1, (int) $setting->ai_max_per_hour)) {
                $thread->forceFill(['needs_human' => true])->save();

                return;
            }

            $text = $this->draft($thread);

            if ($text === null) {
                $thread->forceFill(['needs_human' => true])->save();

                return;
            }

            $this->inbox->reply($thread, $text, null, 'ai');
        } catch (SocialException $e) {
            $thread?->forceFill(['needs_human' => true])->save();
        } catch (Throwable $e) {
            report($e);
            $thread?->forceFill(['needs_human' => true])->save();
        } finally {
            $lock->release();
        }
    }

    /** Pesan masuk yang belum dibalas mengandung topik sensitif. */
    private function sensitive(SocialThread $thread): bool
    {
        $texts = $thread->messages()->where('direction', 'in')->orderByDesc('sent_at')->limit(3)->pluck('text');

        return $texts->contains(fn ($text) => preg_match(self::SENSITIVE, (string) $text) === 1);
    }

    /** @return list<array{role: 'user'|'assistant', content: string}> 12 pesan terakhir, peran berselang-seling, diakhiri user. */
    private function history(SocialThread $thread): array
    {
        $messages = $thread->messages()->where('status', 'sent')->whereNotNull('text')
            ->orderByDesc('sent_at')->orderByDesc('created_at')->limit(12)->get()->reverse();

        $history = [];

        foreach ($messages as $message) {
            $role = $message->direction === 'in' ? 'user' : 'assistant';
            $text = mb_substr(trim((string) $message->text), 0, 1500);

            if ($text === '') {
                continue;
            }

            if ($history !== [] && $history[array_key_last($history)]['role'] === $role) {
                $history[array_key_last($history)]['content'] .= "\n".$text;
            } else {
                $history[] = ['role' => $role, 'content' => $text];
            }
        }

        while ($history !== [] && $history[0]['role'] !== 'user') {
            array_shift($history);
        }

        while ($history !== [] && $history[array_key_last($history)]['role'] !== 'user') {
            array_pop($history);
        }

        return $history;
    }

    private function system(SocialInboxSetting $setting, SocialThread $thread): string
    {
        $where = $thread->account?->platformLabel().($thread->kind === SocialThread::KIND_DM ? ' (pesan pribadi)' : ' (balasan komentar publik)');

        return trim((string) ($setting->ai_instructions ?: SocialInboxSetting::DEFAULT_INSTRUCTIONS))."\n\n"
            ."Kamu membalas di {$where} atas nama akun resmi bizbos.\n"
            ."Aturan:\n"
            ."- Bahasa Indonesia santai tapi sopan, singkat (maks 3 kalimat), tanpa markdown.\n"
            ."- Hanya pakai informasi di atas. Jangan mengarang fitur, harga, diskon, jadwal, atau janji.\n"
            ."- Jangan pernah meminta password, OTP, PIN, nomor rekening, atau data pribadi.\n"
            .($thread->kind === SocialThread::KIND_COMMENT ? "- Ini komentar publik: jangan bahas data pribadi; arahkan ke DM atau app.bizbos.id kalau perlu detail.\n" : '')
            .'- Kalau pertanyaannya komplain, soal pembayaran/akun pribadi, di luar informasi di atas, atau kamu tidak yakin: jawab persis '.self::HANDOFF.' saja.';
    }
}
