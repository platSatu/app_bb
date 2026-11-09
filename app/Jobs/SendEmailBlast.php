<?php

namespace App\Jobs;

use App\Services\Marketing\EmailBlastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim Tools › Email Blast sedikit demi sedikit (EmailBlastService::BATCH penerima per putaran), lalu lanjut di job
 * berikutnya dengan jeda: tiap putaran singkat (di bawah retry_after antrean) dan pengiriman tidak membanjiri server email.
 * Hasil per penerima disimpan, jadi job yang terulang tidak mengirim dua kali ke alamat yang sama.
 */
class SendEmailBlast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 80;

    public function __construct(public string $blastId)
    {
        $this->afterCommit();
    }

    public function handle(EmailBlastService $service): void
    {
        if ($service->sendBatch($this->blastId)) {
            self::dispatch($this->blastId)->delay(now()->addSeconds(5));
        }
    }
}
