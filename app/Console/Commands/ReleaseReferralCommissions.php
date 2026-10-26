<?php

namespace App\Console\Commands;

use App\Services\Referral\ReferralService;
use Illuminate\Console\Command;

class ReleaseReferralCommissions extends Command
{
    protected $signature = 'referrals:release';

    protected $description = 'Cairkan komisi referral yang masa tahannya sudah lewat ke saldo pengajak';

    public function handle(ReferralService $referrals): int
    {
        $this->info('Komisi cair: '.$referrals->releaseDue());

        return self::SUCCESS;
    }
}
