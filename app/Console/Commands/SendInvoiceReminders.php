<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Invoice\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Email pengingat N hari sebelum jatuh tempo (0 = di hari jatuh tempo), sesuai pengaturan kategori. Sekali per hari pengingat. */
class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:remind';

    protected $description = 'Kirim email pengingat invoice yang mendekati jatuh tempo';

    public function handle(InvoiceService $invoices): int
    {
        $sent = 0;

        Invoice::where('status', Invoice::STATUS_UNPAID)
            ->whereNotNull('customer_email')
            ->whereNotNull('category_id')
            ->whereBetween('due_date', [today(), today()->addDays(60)])
            ->with('category')
            ->chunkById(200, function ($items) use ($invoices, &$sent) {
                foreach ($items as $invoice) {
                    $days = (int) today()->diffInDays($invoice->due_date, false);

                    if (! $invoice->category?->email_enabled || ! in_array($days, $invoice->category->reminder_days ?? [], true)) {
                        continue;
                    }

                    // Klaim dulu; kalau command jalan dua kali, pengingat tetap terkirim sekali.
                    $claimed = DB::table('invoice_reminder_logs')->insertOrIgnore(['invoice_id' => $invoice->getKey(), 'days_before' => $days, 'sent_at' => now()]);

                    if ($claimed && $invoices->email($invoice, 'reminder')) {
                        $sent++;
                    }
                }
            });

        $this->info("{$sent} email pengingat dikirim.");

        return self::SUCCESS;
    }
}
