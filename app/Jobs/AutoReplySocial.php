<?php

namespace App\Jobs;

use App\Services\Social\Inbox\SocialAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Balasan AI untuk satu pesan masuk di Tools › Inbox. Tidak diulang otomatis (supaya tidak membalas dobel). */
class AutoReplySocial implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public string $threadId, public string $messageId) {}

    public function handle(SocialAgent $agent): void
    {
        $agent->autoReply($this->threadId, $this->messageId);
    }
}
