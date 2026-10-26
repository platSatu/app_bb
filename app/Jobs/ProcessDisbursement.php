<?php

namespace App\Jobs;

use App\Services\Withdrawal\DisbursementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proses satu disbursement di antrean (inquiry + transfer bisa lama, website pemanggil tidak ikut menunggu).
 * Sengaja hanya 1 percobaan: mengulang transfer otomatis berisiko uang keluar dua kali.
 * Kalau worker mati di tengah proses, status tetap "Diproses" dan muncul di daftar cek tim bizbos.
 */
class ProcessDisbursement implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Inquiry + transfer masing-masing maks 30 detik. Harus di bawah retry_after antrean (90 detik),
    // supaya job yang masih berjalan tidak dianggap macet lalu diambil worker lain.
    public int $timeout = 80;

    public function __construct(public string $disbursementId)
    {
        $this->afterCommit();
    }

    public function handle(DisbursementService $service): void
    {
        $service->process($this->disbursementId);
    }
}
