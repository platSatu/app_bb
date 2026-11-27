<?php

namespace App\Console\Commands;

use App\Models\UserBranch;
use App\Services\Schedule\ScheduleReminderService;
use App\Services\Subscription\AppAccess;
use App\Support\ScheduleNotice;
use Illuminate\Console\Command;

/**
 * Pengingat WhatsApp aplikasi Jadwal, dijalankan tiap 5 menit (paket WhatsApp & Jadwal aktif, sakelar di Schedules › Notifikasi):
 *  - murid / orang tua H-1, mulai jam yang dipilih branch (bawaan 16:00);
 *  - murid / orang tua sesaat sebelum les (bawaan 1 jam sebelumnya);
 *  - rekap mengajar besok untuk pengajar, mulai jam yang dipilih branch (bawaan 18:00).
 * Pengirimannya di ScheduleReminderService (dipakai juga menu admin WhatsApp); tiap penerima hanya dikirimi
 * sekali per sesi / per hari (penanda di cache), jadi aman dijalankan berulang.
 */
class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:remind';

    protected $description = 'Kirim pengingat les ke murid & rekap mengajar ke pengajar lewat WhatsApp';

    public function handle(ScheduleReminderService $reminders): int
    {
        $sent = 0;
        $now = now()->format('H:i');
        $tomorrow = [today()->addDay(), today()->addDay()->endOfDay()];

        UserBranch::whereIn('id', AppAccess::activeBranches('schedules'))->whereIn('id', AppAccess::activeBranches('whatsapp'))
            ->whereNotNull('wa_notify')->get(['id', 'wa_notify'])
            ->each(function (UserBranch $branch) use (&$sent, $now, $tomorrow, $reminders) {
                $on = fn (string $kind) => ! empty($branch->wa_notify[$kind]);

                if ($on('schedule') && $now >= ScheduleNotice::setting($branch, 'schedule_at')) {
                    $sent += $reminders->students($branch, $tomorrow, false);
                }

                if ($on('schedule_soon')) {
                    $sent += $reminders->students($branch, [now(), now()->addMinutes((int) ScheduleNotice::setting($branch, 'schedule_soon_minutes'))], true);
                }

                if ($on('teacher_recap') && $now >= ScheduleNotice::setting($branch, 'teacher_recap_at')) {
                    $sent += $reminders->teachers($branch, $tomorrow);
                }
            });

        $this->info("{$sent} pesan pengingat Jadwal diantrekan.");

        return self::SUCCESS;
    }
}
