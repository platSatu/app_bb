<?php

namespace App\Support;

use App\Models\EmailLog;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Mencatat SEMUA email ke tabel email_logs tanpa mengubah kelas email-nya:
 * masuk antrean (queued) -> diterima server email (sent) / gagal (failed, dengan alasan).
 * Didaftarkan sebagai singleton + subscriber di AppServiceProvider. Pencatatan tidak boleh
 * menggagalkan pengiriman email, jadi semua error di sini hanya dilaporkan.
 */
class EmailLogRecorder
{
    /** uuid job antrean yang sedang dijalankan worker (untuk menyambungkan MessageSent ke barisnya). */
    private ?string $currentJob = null;

    public function subscribe(Dispatcher $events): array
    {
        return [
            JobQueued::class => 'queued',
            JobProcessing::class => 'processing',
            JobProcessed::class => 'processed',
            MessageSent::class => 'sent',
            JobExceptionOccurred::class => 'failed',
            JobFailed::class => 'failed',
        ];
    }

    public function queued(JobQueued $event): void
    {
        $this->safely(function () use ($event) {
            if ($email = $this->describe($event->job)) {
                EmailLog::create($email + ['job_uuid' => $event->payload()['uuid'] ?? null, 'status' => EmailLog::STATUS_QUEUED]);
            }
        });
    }

    public function processing(JobProcessing $event): void
    {
        $this->currentJob = $event->job->uuid();
    }

    public function processed(): void
    {
        $this->currentJob = null;
    }

    public function sent(MessageSent $event): void
    {
        $this->safely(function () use ($event) {
            $data = [
                'status' => EmailLog::STATUS_SENT,
                'subject' => Str::limit((string) $event->message->getSubject(), 250, ''),
                'error' => null,
                'sent_at' => now(),
            ];

            $updated = $this->currentJob ? EmailLog::where('job_uuid', $this->currentJob)->update($data) : 0;

            // Email yang tidak lewat antrean (atau belum tercatat): buat barisnya sekarang.
            if (! $updated) {
                EmailLog::create($data + [
                    'type' => $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? 'Email',
                    'recipient' => $this->recipients(array_map(fn (Address $to) => $to->getAddress(), $event->message->getTo())),
                    'job_uuid' => $this->currentJob,
                ]);
            }
        });
    }

    /** Dipanggil tiap percobaan gagal & saat antrean menyerah; kalau percobaan berikutnya berhasil, status jadi Terkirim. */
    public function failed(JobExceptionOccurred|JobFailed $event): void
    {
        $this->safely(fn () => EmailLog::where('job_uuid', $event->job->uuid())
            ->where('status', '!=', EmailLog::STATUS_SENT)
            ->update(['status' => EmailLog::STATUS_FAILED, 'error' => Str::limit($event->exception->getMessage(), 480, '')]));
    }

    /** @return array{type: string, recipient: string}|null  null = job ini bukan email. */
    private function describe(mixed $job): ?array
    {
        if ($job instanceof SendQueuedMailable) {
            return ['type' => get_class($job->mailable), 'recipient' => $this->recipients(array_column($job->mailable->to, 'address'))];
        }

        if ($job instanceof SendQueuedNotifications && in_array('mail', (array) $job->channels, true)) {
            $to = collect($job->notifiables)->map(function ($notifiable) use ($job) {
                $route = $notifiable->routeNotificationFor('mail', $job->notification);

                // Rute bisa "a@b.c", ["a@b.c" => "Nama"], atau ["a@b.c", ...].
                return is_array($route) ? array_map(fn ($key, $value) => is_string($key) ? $key : $value, array_keys($route), $route) : $route;
            })->flatten()->filter()->all();

            return ['type' => get_class($job->notification), 'recipient' => $this->recipients($to)];
        }

        return null;
    }

    private function recipients(array $emails): string
    {
        return Str::limit(implode(', ', $emails) ?: '-', 495, '');
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
