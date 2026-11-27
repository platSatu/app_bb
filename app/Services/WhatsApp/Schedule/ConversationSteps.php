<?php

namespace App\Services\WhatsApp\Schedule;

use App\Models\WaScheduleConversation as Conversation;
use App\Services\WhatsApp\Bot\BotReply;
use App\Support\ScheduleBotText as Text;
use DateTimeInterface;

/** Langkah percakapan yang sama untuk bot murid, admin & pengajar: mulai, lanjut, selesai, salah jawab. */
trait ConversationSteps
{
    protected function start(string $branchId, string $phone, string $role, string $step, array $context, DateTimeInterface $expires): Conversation
    {
        Conversation::where('branch_id', $branchId)->where('phone', $phone)->delete();

        $conversation = new Conversation();
        $conversation->forceFill(['branch_id' => $branchId, 'phone' => $phone, 'role' => $role, 'step' => $step, 'context' => $context, 'invalid_count' => 0, 'expires_at' => $expires])->save();

        return $conversation;
    }

    /** Pindah langkah. Percakapan murid & pengajar tidak pernah melewati batas waktu awalnya (jam les). */
    protected function next(Conversation $conversation, string $step, array $context, string $text, int $idleMinutes = 60): BotReply
    {
        $expires = now()->addMinutes($idleMinutes);

        if ($conversation->role !== Conversation::ROLE_ADMIN && $conversation->expires_at->lt($expires)) {
            $expires = $conversation->expires_at;
        }

        $conversation->forceFill(['step' => $step, 'context' => [...($conversation->context ?? []), ...$context], 'invalid_count' => 0, 'expires_at' => $expires])->save();

        return new BotReply($text);
    }

    /** Selesai: catat (opsional) lalu tutup percakapan. */
    protected function finish(Conversation $conversation, ?string $text, ?callable $record = null): ?BotReply
    {
        $record && $record();
        $conversation->delete();

        return $text === null ? null : new BotReply($text);
    }

    /** Salah jawab: diulang paling banyak 2 kali, setelah itu bot menyingkir supaya admin / AI bisa membantu. */
    protected function invalid(Conversation $conversation, string $options, bool $bare = false): ?BotReply
    {
        if ($conversation->invalid_count >= 2) {
            $conversation->delete();

            return null;
        }

        $conversation->forceFill(['invalid_count' => $conversation->invalid_count + 1])->save();

        return new BotReply($bare ? $options : Text::invalid($options));
    }

    /** Angka tunggal "1", "*2*", "3." -> 3; selain itu 0. */
    protected function number(string $text): int
    {
        return preg_match('/^\*?(\d{1,2})\*?[.)]?$/', $text, $m) ? (int) $m[1] : 0;
    }
}
