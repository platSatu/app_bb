<?php

namespace App\Console\Commands;

use App\Models\ScheduleRoutine;
use App\Services\Schedule\ScheduleRoutineService;
use Illuminate\Console\Command;
use Throwable;

/** Menyiapkan sesi jadwal rutin aktif sampai akhir bulan depan. Aman dijalankan berulang (tidak membuat sesi kembar). */
class GenerateScheduleSessions extends Command
{
    protected $signature = 'schedules:generate';

    protected $description = 'Buat sesi dari jadwal rutin yang aktif sampai akhir bulan depan';

    public function handle(ScheduleRoutineService $routines): int
    {
        $created = 0;

        ScheduleRoutine::active()
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
            ->chunkById(100, function ($items) use ($routines, &$created) {
                foreach ($items as $routine) {
                    try {
                        $created += $routines->extend($routine);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("{$created} sesi baru dibuat.");

        return self::SUCCESS;
    }
}
