<?php

namespace App\Jobs;

use App\Models\ScheduleStudent;
use App\Models\User;
use App\Services\Schedule\ScheduleBillingService;
use App\Support\ScheduleNotice;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Buat tagihan les semua murid aktif satu branch (dari menu admin WhatsApp), sama dengan tombol Buat Tagihan di menu Murid.
 * Di antrean karena muridnya bisa ratusan; hasilnya dikirim ke WhatsApp admin. Tagihan yang sudah ada dilewati (aman diulang).
 */
class BillScheduleStudents implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $branchId, public string $actorId, public string $month, public string $adminPhone)
    {
        $this->afterCommit();
    }

    public function handle(ScheduleBillingService $billing): void
    {
        $actor = User::find($this->actorId);

        if (! $actor) {
            return;
        }

        $month = Carbon::createFromFormat('!Y-m', $this->month);
        $students = ScheduleStudent::where('branch_id', $this->branchId)->where('status', ScheduleStudent::STATUS_ACTIVE)->orderBy('name')->get();
        $result = $billing->bill($students, $month, $actor);

        SendWaNotification::queue($this->branchId, ScheduleNotice::BOT, $this->adminPhone, \App\Support\ScheduleBotText::billResult($result, $month));
    }
}
