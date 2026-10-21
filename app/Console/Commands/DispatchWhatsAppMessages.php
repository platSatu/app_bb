<?php

namespace App\Console\Commands;

use App\Services\Subscription\AppAccess;
use App\Models\WaBroadcast;
use App\Models\WaCampaign;
use App\Services\WhatsApp\BroadcastService;
use Illuminate\Console\Command;

class DispatchWhatsAppMessages extends Command
{
    protected $signature = 'whatsapp:dispatch-messages';

    protected $description = 'Jalankan Message WhatsApp yang sudah waktunya dan mulai ulang campaign yang belum sempat dimulai';

    public function handle(BroadcastService $broadcasts): int
    {
        $ran = 0;
        WaBroadcast::where('status', 'active')->whereIn('branch_id', AppAccess::activeBranches('whatsapp'))->where('next_run_at', '<=', now())->orderBy('next_run_at')->limit(50)->pluck('id')
            ->each(function (string $id) use ($broadcasts, &$ran) {
                $ran += $broadcasts->run($id) ? 1 : 0;
            });

        // Campaign yang dibuat saat go_bizbos tidak bisa dihubungi.
        $retried = 0;
        WaCampaign::where('status', 'draft')->whereIn('branch_id', AppAccess::activeBranches('whatsapp'))->whereNotNull('broadcast_id')->where('created_at', '<=', now()->subMinute())->limit(50)->get()
            ->each(function (WaCampaign $campaign) use ($broadcasts, &$retried) {
                $retried += $broadcasts->start($campaign) ? 1 : 0;
            });

        $this->info("Dijalankan: {$ran}, dimulai ulang: {$retried}");

        return self::SUCCESS;
    }
}
