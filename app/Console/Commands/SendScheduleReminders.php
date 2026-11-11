<?php

namespace App\Console\Commands;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\UserBranch;
use App\Services\Subscription\AppAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pengingat les H-1 lewat WhatsApp ke murid / orang tua, hanya untuk branch yang menyalakan notifikasi Jadwal
 * (paket WhatsApp & Jadwal aktif). Aman dijalankan ulang di hari yang sama: tiap murid per sesi diingatkan sekali.
 */
class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:remind';

    protected $description = 'Kirim pengingat les besok lewat WhatsApp (branch yang menyalakan notifikasi Jadwal)';

    public function handle(): int
    {
        $branches = UserBranch::whereIn('id', AppAccess::activeBranches('schedules'))->whereIn('id', AppAccess::activeBranches('whatsapp'))
            ->whereNotNull('wa_notify')->get(['id', 'wa_notify'])->filter(fn (UserBranch $b) => ! empty($b->wa_notify['schedule']))->modelKeys();
        $sent = 0;

        ScheduleSession::with(['branch:id,name', 'teacher:id,name', 'room:id,name', 'grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name',
            'attendances' => fn ($q) => $q->where('status', ScheduleAttendance::STATUS_PENDING)->with('student:id,name,phone,guardian_name')])
            ->whereIn('branch_id', $branches ?: ['-'])
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->whereBetween('start_at', [today()->addDay(), today()->addDay()->endOfDay()])
            ->chunkById(100, function ($sessions) use (&$sent) {
                foreach ($sessions as $session) {
                    foreach ($session->attendances as $attendance) {
                        if ($attendance->student?->phone && Cache::add('schedule-remind:'.$attendance->getKey(), 1, now()->addDays(2))
                            && SendWaNotification::lesson($session, $attendance->student)) {
                            $sent++;
                        }
                    }
                }
            });

        $this->info("{$sent} pengingat les dikirim lewat WhatsApp.");

        return self::SUCCESS;
    }
}
