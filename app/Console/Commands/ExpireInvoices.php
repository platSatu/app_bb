<?php

namespace App\Console\Commands;

use App\Services\Invoice\InvoiceCheckoutService;
use App\Services\Invoice\InvoicePaymentService;
use Illuminate\Console\Command;

class ExpireInvoices extends Command
{
    protected $signature = 'invoices:expire';

    protected $description = 'Cek pembayaran invoice yang lewat batas ke Duitku, lalu tutup invoice API yang kedaluwarsa';

    public function handle(InvoicePaymentService $payments, InvoiceCheckoutService $checkouts): int
    {
        $summary = $payments->expireStale();
        $group = $checkouts->expireStale();

        $this->info("Lunas: {$summary['paid']}, pembayaran kedaluwarsa: {$summary['expired_payments']}, invoice kedaluwarsa: {$summary['expired_invoices']}");
        $this->info("Bayar sekaligus (Tagihan) - lunas: {$group['paid']}, kedaluwarsa: {$group['expired']}");

        return self::SUCCESS;
    }
}
