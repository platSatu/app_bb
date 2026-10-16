<?php

namespace App\Console\Commands;

use App\Services\Deposit\DepositService;
use Illuminate\Console\Command;

class ExpireDeposits extends Command
{
    protected $signature = 'deposits:expire';

    protected $description = 'Tandai deposit pending yang lewat batas waktu sebagai Kedaluwarsa (dicek dulu ke Duitku)';

    public function handle(DepositService $deposits): int
    {
        $summary = $deposits->expireStale();

        $this->info("Dikreditkan: {$summary['credited']}, kedaluwarsa: {$summary['expired']}, dilewati: {$summary['skipped']}");

        return self::SUCCESS;
    }
}
