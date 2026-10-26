<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogOrderService;
use Illuminate\Console\Command;

class ExpireCatalogOrders extends Command
{
    protected $signature = 'catalog:expire';

    protected $description = 'Batalkan pesanan katalog bayar online yang lewat batas bayar & kembalikan stoknya (dicek dulu ke Duitku)';

    public function handle(CatalogOrderService $orders): int
    {
        $summary = $orders->expireStale();

        $this->info("Lunas: {$summary['paid']}, dibatalkan: {$summary['expired']}, dilewati: {$summary['skipped']}");

        return self::SUCCESS;
    }
}
