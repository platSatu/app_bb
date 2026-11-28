<?php

namespace App\Jobs;

use App\Services\Social\Inbox\SocialInbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Isi webhook Meta (sudah lolos cek tanda tangan) diproses di antrean supaya Meta langsung mendapat 200. */
class ProcessMetaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public array $payload) {}

    public function handle(SocialInbox $inbox): void
    {
        $inbox->ingestMeta($this->payload);
    }
}
