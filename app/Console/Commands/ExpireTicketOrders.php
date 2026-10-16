<?php

namespace App\Console\Commands;

use App\Services\Ticket\TicketOrderService;
use Illuminate\Console\Command;

class ExpireTicketOrders extends Command
{
    protected $signature = 'tickets:expire';

    protected $description = 'Tandai pesanan tiket yang lewat batas bayar sebagai Kedaluwarsa dan lepas kuota yang ditahan (dicek dulu ke Duitku)';

    public function handle(TicketOrderService $orders): int
    {
        $summary = $orders->expireStale();

        $this->info("Lunas: {$summary['paid']}, kedaluwarsa: {$summary['expired']}, dilewati: {$summary['skipped']}");

        return self::SUCCESS;
    }
}
