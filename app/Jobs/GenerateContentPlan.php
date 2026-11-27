<?php

namespace App\Jobs;

use App\Services\Marketing\ContentAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Satu putaran "Buat rencana" Agent Konten (3 slot per putaran), lalu menjadwalkan putaran berikutnya
 * sampai slot habis. Tidak diulang otomatis: putaran yang gagal dicatat sebagai slot gagal.
 */
class GenerateContentPlan implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public string $batchId)
    {
        $this->afterCommit();
    }

    public function handle(ContentAgent $agent): void
    {
        if ($agent->generateNext($this->batchId)) {
            self::dispatch($this->batchId);
        }
    }
}
