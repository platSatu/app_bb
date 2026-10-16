<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Event WhatsApp dari go_bizbos, untuk didengarkan fitur lain (notifikasi, auto-reply, dll). */
class WaWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public string $event,
        public string $branchId,
        public array $data,
    ) {}
}
