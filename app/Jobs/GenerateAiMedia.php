<?php

namespace App\Jobs;

use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Services\Ai\ClassaiMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Buat gambar / suara / video untuk Asisten AI di antrean (permintaan web tidak menunggu sampai beberapa menit).
 *
 * - Gambar & suara: satu kali jalan. Pesan "diklaim" dulu (pending -> processing), jadi kalau antrean
 *   menjalankan ulang job yang masih berjalan (retry_after), job kedua langsung berhenti tanpa memanggil AI lagi.
 * - Video: jalan pertama memulai tugas di Classai, lalu job ini menjadwalkan dirinya lagi tiap 15 detik
 *   untuk mengecek status sampai selesai/gagal (dibatalkan setelah 45 menit, sesuai dokumentasi Classai).
 */
class GenerateAiMedia implements ShouldQueue
{
    use Queueable;

    public const POLL_SECONDS = 15;

    public const VIDEO_LIMIT_MINUTES = 45;

    public int $tries = 3;

    public int $timeout = 330;

    public function __construct(public string $messageId)
    {
        $this->afterCommit();
    }

    public function handle(ClassaiMedia $media): void
    {
        $message = AiMessage::with('conversation')->find($this->messageId);

        if (! $message || ! $message->isWaiting() || ! $message->conversation) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider?->isClassai()) {
            $this->markFailed($message, 'Penyedia AI yang aktif bukan Classai Router. Gambar, video & suara hanya tersedia lewat Classai.');

            return;
        }

        if ($message->kind === AiMessage::KIND_VIDEO) {
            $this->video($message, $provider, $media);

            return;
        }

        if (! $this->claim($message)) {
            return;
        }

        $options = (array) $message->options;
        $start = hrtime(true);

        try {
            $result = $message->kind === AiMessage::KIND_AUDIO
                ? $media->speech($provider, $message->model, [
                    'text' => $options['prompt'] ?? '', 'voice' => $options['voice'] ?? '',
                    'style' => $options['style'] ?? null, 'speed' => $options['speed'] ?? null,
                ])
                : $media->image($provider, $message->model, [
                    'prompt' => $options['prompt'] ?? '', 'aspect_ratio' => $options['aspect_ratio'] ?? null,
                    'resolution' => $options['resolution'] ?? null, 'quality' => $options['quality'] ?? null,
                    'n' => $options['n'] ?? 1, 'reference' => $options['reference'] ?? null,
                ]);
        } catch (RuntimeException $e) {
            $this->markFailed($message, $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($message, 'Terjadi kesalahan saat membuat hasil. Coba lagi.');

            return;
        }

        $this->finish($message, $media, $result['files'], $result['cost_micro_rp'], (int) round((hrtime(true) - $start) / 1_000_000));
    }

    private function video(AiMessage $message, AiProvider $provider, ClassaiMedia $media): void
    {
        $options = (array) $message->options;

        // Tahap 1: mulai tugas di Classai.
        if (! $message->job_id) {
            if (! $this->claim($message)) {
                return;
            }

            try {
                $message->job_id = $media->startVideo($provider, $message->model, [
                    'prompt' => $options['prompt'] ?? '', 'aspect_ratio' => $options['aspect_ratio'] ?? null,
                    'resolution' => $options['resolution'] ?? null, 'seconds' => $options['seconds'] ?? null,
                    'reference' => $options['reference'] ?? null,
                ]);
            } catch (RuntimeException $e) {
                $this->markFailed($message, $e->getMessage());

                return;
            }

            $message->options = [...$options, 'started_at' => now()->toIso8601String()];
            $message->save();
            self::pollLater($message->getKey());

            return;
        }

        // Tahap 2: cek status (satu pengecekan dalam satu waktu per pesan).
        Cache::lock('ai-media-video:'.$message->getKey(), 320)->get(function () use ($message, $provider, $media, $options) {
            $message->refresh();

            if (! $message->isWaiting()) {
                return;
            }

            try {
                $state = $media->videoStatus($provider, $message->job_id);
            } catch (RuntimeException $e) {
                // Gangguan sesaat: coba cek lagi nanti, kecuali sudah melewati batas waktu.
                $this->retryOrExpire($message, $options);

                return;
            }

            if ($state['status'] === 'failed') {
                $this->markFailed($message, 'Video gagal dibuat oleh Classai'.($state['error'] ? ': '.$state['error'] : '.').' Saldo tidak terpotong.');

                return;
            }

            if ($state['status'] !== 'completed') {
                $this->retryOrExpire($message, $options);

                return;
            }

            try {
                $file = $media->downloadVideo($provider, $message->job_id);
            } catch (RuntimeException $e) {
                $this->retryOrExpire($message, $options);

                return;
            }

            $started = isset($options['started_at']) ? \Illuminate\Support\Carbon::parse($options['started_at']) : $message->created_at;
            $this->finish($message, $media, [$file], null, (int) $started->diffInMilliseconds(now()));
        });
    }

    private function retryOrExpire(AiMessage $message, array $options): void
    {
        $started = isset($options['started_at']) ? \Illuminate\Support\Carbon::parse($options['started_at']) : $message->created_at;

        if ($started->diffInMinutes(now()) >= self::VIDEO_LIMIT_MINUTES) {
            $this->markFailed($message, 'Video tidak selesai dalam '.self::VIDEO_LIMIT_MINUTES.' menit. Classai membatalkannya dan mengembalikan kredit.');

            return;
        }

        self::pollLater($message->getKey());
    }

    /**
     * Jadwalkan pengecekan video berikutnya. Dengan antrean "sync" (biasanya di komputer lokal) jeda tidak berlaku,
     * jadi pengecekan dilakukan saat halaman chat menanyakan status (lihat AssistantMediaController::status).
     */
    public static function pollLater(string $messageId): void
    {
        if (config('queue.default') !== 'sync') {
            self::dispatch($messageId)->delay(now()->addSeconds(self::POLL_SECONDS));
        }
    }

    /** pending -> processing secara atomik; false kalau sudah diambil worker lain. */
    private function claim(AiMessage $message): bool
    {
        $claimed = AiMessage::whereKey($message->getKey())->where('status', AiMessage::STATUS_PENDING)
            ->update(['status' => AiMessage::STATUS_PROCESSING, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $message->status = AiMessage::STATUS_PROCESSING;
        }

        return $claimed;
    }

    private function finish(AiMessage $message, ClassaiMedia $media, array $files, ?int $cost, int $durationMs): void
    {
        $stored = array_map(fn ($file) => $media->store($message->conversation_id, $file), $files);
        $options = (array) $message->options;

        $message->forceFill([
            'media' => $stored,
            'options' => array_filter([...$options, 'cost_micro_rp' => $cost], fn ($v) => $v !== null),
            'status' => AiMessage::STATUS_DONE,
            'duration_ms' => $durationMs,
            'content' => match ($message->kind) {
                AiMessage::KIND_IMAGE => count($stored) > 1 ? count($stored).' gambar selesai dibuat.' : 'Gambar selesai dibuat.',
                AiMessage::KIND_VIDEO => 'Video selesai dibuat.',
                default => 'Suara selesai dibuat. Beri tahu pendengar bahwa audio ini dibuat dengan AI.',
            },
        ])->save();

        $this->dropReference($options);
        $message->conversation?->touch();
    }

    private function markFailed(AiMessage $message, string $reason): void
    {
        $options = (array) $message->options;
        $message->forceFill(['status' => AiMessage::STATUS_FAILED, 'content' => $reason])->save();
        $this->dropReference($options);
    }

    /** Gambar referensi hanya dipakai sekali; dihapus setelah selesai/gagal. */
    private function dropReference(array $options): void
    {
        if (filled($options['reference'] ?? null)) {
            Storage::disk(ClassaiMedia::DISK)->delete($options['reference']);
        }
    }
}
