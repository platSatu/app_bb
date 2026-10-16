<?php

namespace App\Jobs;

use App\Services\WhatsApp\Bot\BotRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Jawaban otomatis (flow / auto reply / AI) untuk satu pesan masuk. */
class ProcessIncomingWaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public string $messageId) {}

    public function handle(BotRouter $router): void
    {
        $router->handle($this->messageId);
    }
}
